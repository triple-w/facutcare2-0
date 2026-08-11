# FC2: mapa completo del flujo fiscal

**Fecha de revisión:** 2026-07-27  
**Método:** inspección estática del repositorio. No se ejecutaron operaciones PAC/SAT, timbrados, cancelaciones, migraciones ni consultas de base de datos.  
**Convenciones:** **Confirmado** = existe código alcanzable desde una ruta; **Legado** = existe, pero no se encontró invocación desde el flujo actual; **Inferencia** = conclusión razonable no verificable sin esquema/servicio; **Recomendación** = diseño propuesto para iKontrol2.0.

> Este documento no contiene valores de secretos. Los literales sensibles detectados se describen únicamente por nombre y ubicación.

## 1. Resumen ejecutivo

FC2 es Laravel 11. El módulo fiscal no está encapsulado como dominio: se concentra en `FacturasController` (CFDI 4.0 de ingreso, egreso y traslado), `ComplementosController` (CFDI 4.0 con Pagos 2.0), `PacMultiPacTrait`, `MultiPac` y `ConfiguracionController`.

El flujo activo construye XML local con `DOMDocument`, carga CSD desde BD/archivos bajo `public/uploads`, inserta `Certificado` y `NoCertificado`, deja `Sello=""` y llama por SOAP a TimbradorXpress/MultiPac `timbrarConSello(apikey, xmlCFDI, keyPEM)`. Por tanto, el sellado efectivo se delega al PAC. Después intenta generar PDF por WSTools33 (`generarPDF`) y, para facturas, cae a Dompdf. Finalmente persiste XML timbrado, UUID, PDF Base64, solicitud previa y metadatos en BD.

Cancelación es una operación SOAP separada (`cancelarPEM`). El código marca inmediatamente `CANCELADA` si interpreta éxito y guarda el acuse devuelto; no modela solicitud, aceptación, pendiente ni reconciliación SAT. No existe consulta activa de estatus.

Persistencia observada:

- BD: cabeceras, detalles, impuestos, XML timbrado como texto, XML previo como texto, UUID, PDF como Base64, acuse como texto, estatus, folio y timbres disponibles.
- Archivos: CER, KEY, PEM derivados y logos en `public/uploads`. No se observó XML/PDF fiscal guardado como archivo.
- Seguridad razonable: rutas dentro de `auth:sanctum` + `verified`, consultas principales filtradas por `users_id`, CSRF web predeterminado y transacciones locales.
- Fragilidad: llamada PAC antes de una persistencia durable, sin idempotencia; CSD en webroot; contraseña CSD aparentemente en claro; validación CSD remota sin verificación TLS; credenciales PDF hardcodeadas; WSTools33 por HTTP; controladores monolíticos; esquema fiscal casi ausente de migraciones.

## 2. Arquitectura general

```text
Captura Blade/Alpine
  -> preview() y draft de sesión
  -> timbrar()
  -> generarXmlCfdi40DesdePayload() / generarXmlPagos20DesdePayload()
  -> cargarCsdParaTimbrado()
  -> inyectarCertificadoEnXml() [Sello vacío]
  -> MultiPac::callTimbrarCFDI()
  -> SOAP timbrarConSello()
  -> XML timbrado + UUID
  -> generarPdfBase64...() -> WSTools33; fallback Dompdf
  -> DB::transaction()
       -> guardar...Timbrado()
       -> avanzar folio
       -> consumir timbre
  -> vista/descargas autenticadas
  -> cancelar()
  -> MultiPac::callCancelarPEM()
  -> guardar estatus CANCELADA + acuse + consumir timbre
  -> [consulta posterior: NO IMPLEMENTADA]
```

| Paso | Factura | Pagos 2.0 |
|---|---|---|
| Captura | `facturas/create.blade.php` | `documentos/complementos/create.blade.php` |
| Preview | `FacturasController::preview/renderPreviewFromPayload` | `ComplementosController::preview/renderPreviewFromPayload` |
| XML | `generarXmlCfdi40DesdePayload` | `generarXmlPagos20DesdePayload` |
| PAC | `timbrarConPacMultipac` propio | mismo nombre desde `PacMultiPacTrait` |
| Persistencia | `guardarFacturaTimbrada` | `guardarComplementoTimbrado` |
| PDF | `generarPdfBase64DesdePacV33`; Dompdf | `generarPdfBase64ComplementoPagos2`; fallback defectuoso/incompleto |
| Cancelación | `FacturasController::cancelar` | `ComplementosController::cancelar` |

Separación real: timbrado, PDF y cancelación son llamadas distintas, pero la orquestación permanece dentro de la petición HTTP y del controlador. Correo y consulta no forman parte del flujo activo.

## 3. Matriz de archivos

| Archivo / clase | Métodos principales | Responsabilidad; entrada -> salida | Tablas | Externos / observaciones |
|---|---|---|---|---|
| `routes/web.php` | grupo `documentos` | Expone captura, preview, timbrado, vistas, descargas, regeneración y cancelación | — | Todo bajo `auth:sanctum,verified` |
| `FacturasController` | `create`, `preview`, `timbrar`, `generarXmlCfdi40DesdePayload`, `guardarFacturaTimbrada`, `regenerarPdf`, `cancelar` | Payload -> CFDI 4.0 -> artefactos persistidos | `facturas`, `factura_detalles`, `facturas_impuestos`, `folios`, `users`, clientes/emisor/CSD | TimbradorXpress, WSTools33; controlador de 2,746 líneas |
| `ComplementosController` | `facturasPendientes`, normalizadores/decimales, `timbrar`, `generarXmlPagos20DesdePayload`, `guardarComplementoTimbrado`, `cancelar` | Facturas + pagos -> Pagos 2.0 | `complementos`, `complementos_pagos`, `facturas`, `folios`, `users`, datos emisor | Usa trait PAC; 2,498 líneas |
| `PacMultiPacTrait` | `cargarCsdParaTimbrado`, `timbrarConPacMultipac`, `generarPdfBase64DesdePacV33` | Adaptador CSD/PAC/PDF de complementos | tablas CSD y perfil | Duplica lógica de facturas |
| `MultiPac` | `callTimbrarCFDI`, `callCancelarPEM`, `generatePDFV33`; métodos legacy | Wrapper SOAP | — | TimbradorXpress y WSTools33; mezcla activo/legado |
| `ConfiguracionController` | `uploadCsd`, `validarCsd`, conversiones PEM, `replaceDocumento` | Upload CER/KEY -> validación, PEM y metadatos | `users_perfil`, `users_info_factura`, `users_info_factura_documentos` | REST TotalNot; OpenSSL por `exec` |
| `CsdStatus` | `forUser` | Archivos/metadatos -> semáforo de vigencia | tablas CSD | No valida criptográficamente al timbrar |
| `FoliosController`, `Folio`, `Api\SeriesController` | CRUD, `next` | Configura/consulta series y contador | `folios` | Incremento real en controladores fiscales |
| `Factura`, `FacturaDetalle`, `FacturaImpuesto` | relaciones/casts | ORM parcial | tablas homónimas | Flujo fiscal usa Query Builder |
| `Api\SatController`, `ProductosApiController` | búsquedas | Texto -> catálogos/productos | `clave_prod_serv`, `clave_unidad`, `productos` | Rutas duplicadas |
| `BackfillFacturasTotalCommand` | `handle`, `parseTotalFromXml` | XML histórico -> `facturas.total` | `facturas` | Reparación manual; no job |
| vistas `facturas/*` | Alpine/forms/invoice/pdf/rows | Captura, preview, representación y fallback | — | Parte del cálculo existe en cliente |
| vistas `documentos/complementos/*` | Alpine/forms/invoice/pdf/rows | Captura de Pagos 2.0 y representación | — | Payload fiscal armado en frontend |
| `config/sat.php` | arreglos | Catálogos parciales | — | No autoritativos/versionados |
| `config/timbradorxpress_errors.php` | diccionario | Código -> mensaje | — | Usado en cancelación/PDF |
| `Console\Kernel`, providers de eventos | `schedule` | Programación/listeners | — | Sin reconciliación fiscal programada |

No se encontraron jobs fiscales, listeners fiscales, modelos de complemento, repositorios ni servicios de dominio separados.

## 4. Rutas y endpoints

Todas las rutas siguientes heredan `web`, `auth:sanctum` y `verified`; los métodos mutantes usan CSRF de Laravel porque no están excluidos en `VerifyCsrfToken`.

| HTTP y ruta | Acción | Parámetros / respuesta | Efectos |
|---|---|---|---|
| GET `/documentos/facturas/create` | `FacturasController::create` | query opcional; HTML | Lee clientes, folios y catálogos |
| GET `/documentos/facturas/nueva` | `nueva` | redirect | Alias |
| POST `/documentos/facturas/preview` | `preview` | `payload`; HTML | Normaliza y guarda `factura_draft` |
| GET `/documentos/facturas/preview` | `previewGet` | sesión; HTML | Sólo lectura de draft |
| POST `/documentos/facturas/timbrar` | `timbrar` | `payload`, `modo`; redirect o XML texto en debug | PAC, PDF, inserts, folio, timbre |
| GET `/documentos/facturas/{id}/ver` | `show` | id; HTML | Lee documento del usuario |
| GET `/documentos/facturas/{id}/xml` | `downloadXml` | id; XML attachment | Ninguno |
| GET `/documentos/facturas/{id}/pdf` | `downloadPdf` | id; PDF attachment | Base64 decode |
| GET `/documentos/facturas/{id}/acuse` | `downloadAcuse` | id; XML attachment | Ninguno |
| POST `/documentos/facturas/{id}/regenerar-pdf` | `regenerarPdf` | id; redirect | WSTools/Dompdf y update `pdf` |
| POST `/documentos/facturas/{id}/cancelar` | `cancelar` | `motivo`, `folioSustitucion`; redirect | PAC, estado/acuse, consume timbre |
| GET `/documentos/complementos/create` | `ComplementosController::create` | HTML | Lee clientes y folios |
| POST `/documentos/complementos/preview` | `preview` | payload; HTML | Guarda `complemento_draft` |
| GET `/documentos/complementos/facturas-pendientes` | `facturasPendientes` | cliente; JSON | Lee saldos/CFDI |
| POST `/documentos/complementos/timbrar` | `timbrar` | payload, modo; redirect/XML debug | PAC, PDF, persistencia |
| GET `/documentos/complementos/{id}/ver` | `ver` | id; HTML | Lee cabecera/pagos |
| GET `/documentos/complementos/{id}/xml` | `downloadXml` | id; XML attachment | Ninguno |
| GET `/documentos/complementos/{id}/pdf` | `downloadPdf` | id; PDF attachment | Base64 decode |
| POST `/documentos/complementos/{id}/regenerar-pdf` | `regenerarPdf` | id; redirect | Regenera/update |
| POST `/documentos/complementos/{id}/cancelar` | `cancelar` | motivo/sustitución; redirect | PAC, estado/acuse, saldos, timbre |
| POST `/configuracion/csd` | `ConfiguracionController::uploadCsd` | RFC, password, CER, KEY | Upload, REST, PEM, BD |
| GET `/api/series/next` (dos declaraciones) | `SeriesController::next` | serie/tipo; JSON | Sólo lectura |

No hay rutas de guardar borrador permanente, correo, consulta de estatus ni acuse de complemento. `facturas/rows` apunta a `App\Http\Controllers\Users\FacturasController`, clase inexistente.

## 5. Tipos de documento

| Tipo | Estado confirmado | Implementación |
|---|---|---|
| Ingreso `I` | Activo | `FacturasController`, modelo/tablas factura, vistas `facturas/*`, XML 4.0, PAC/PDF/cancelación |
| Egreso/nota de crédito `E` | Activo como variante | Mismo flujo; `mapTipoComprobanteTexto` -> `EGRESO`; relacionados vienen del payload. No hay controlador/modelo propio |
| Traslado `T` | Aceptado por generador | Mismo controlador; no existe flujo/vista especializada ni evidencia de reglas completas de Carta Porte |
| Pago `P` | Activo | `ComplementosController`, `complementos*`, Pagos 2.0, plantilla `pagos2` |
| Nómina `N` | No implementado | Ruta sólo muestra `coming-soon`; cliente `clientNomina` legado no inicializado |
| Retenciones | No encontrado | Sin rutas, XML, modelos ni tablas localizadas |
| Público en general | Parcial/incompleto | Campos de información global aparecen en payload/XML; no hay flujo separado ni catálogo completo verificable |
| Carta Porte/Comercio exterior | No encontrado | No se localizaron complementos específicos |

## 6. Construcción del XML

**Facturas.** `FacturasController::generarXmlCfdi40DesdePayload` usa `DOMDocument` y namespace `http://www.sat.gob.mx/cfd/4`. Obtiene cliente por `cliente_id`, emisor desde `users_perfil`, crea `Comprobante`, `CfdiRelacionados` si aplica, `Emisor`, `Receptor`, `Conceptos`, traslados/retenciones por concepto y totales globales. `calcularResumenFactura` normaliza conceptos, descuentos e impuestos y agrupa por impuesto/tipo/tasa. Se usan `float`, `round` y `number_format` (`fmt`), por lo que la precisión no es una política decimal centralizada. El XML previo no se persiste antes de llamar al PAC: sólo queda en sesión/memoria y se guarda como `solicitud_timbre` después del éxito.

**Pagos.** `ComplementosController::generarXmlPagos20DesdePayload` crea CFDI 4.0 tipo `P`, concepto estándar de pago y complemento `http://www.sat.gob.mx/Pagos20`. Construye `Totales`, `Pago`, `DoctoRelacionado`, `ImpuestosDR` e `ImpuestosP`. Recupera impuestos originales con `extractPago20SourceTaxesFromFacturaXml` y normaliza con `normalizePago20DocumentTaxes`. Los helpers `decimalToScaledInt`, `formatScaledInt`, `roundDivide`, `moneyToCents`, `rateToMicros`, `prorateMoney` evitan gran parte del error binario. `validatePagos20TaxConsistency` valida coherencia interna, no XSD/cadena SAT.

No se encontró validación XSD/XSLT local, cadena original local, ni almacenamiento durable del XML previo antes de timbrar. Los esquemas/URLs SAT se escriben como atributos, pero no se verificaron contra red.

## 7. Sellado

1. `ConfiguracionController::uploadCsd` recibe CER, KEY y contraseña.
2. `storeDocumentoArchivo` guarda originales bajo `public/uploads/...`.
3. `validarCsd` envía RFC, password, CER y KEY a un validador REST externo.
4. `convertCerToPem` ejecuta OpenSSL para DER -> PEM.
5. `convertKeyToPem` ejecuta OpenSSL para descifrar/convertir llave -> PEM.
6. `replaceDocumento` registra archivos/metadatos; `users_info_factura.password` recibe la contraseña.
7. Al timbrar, `cargarCsdParaTimbrado` localiza `ARCHIVO_CERTIFICADO` y `ARCHIVO_LLAVE`, lee CER/PEM, deriva `NoCertificado` y devuelve `cert_b64`, `no_certificado`, `key_pem`.
8. `inyectarCertificadoEnXml` agrega certificado y número, y fuerza `Sello=""`.
9. `MultiPac::callTimbrarCFDI` envía XML textual y `keyPEM`; el PAC sella/timbra y devuelve XML.

El Base64 enviado dentro del XML es el certificado DER. La llave se envía como PEM, no Base64, a `timbrarConSello`. Los métodos `MultiPac::generateSello/generateSelloV33` existen, pero no se encontraron en el flujo activo.

## 8. Timbrado

| Elemento | Evidencia |
|---|---|
| Clase/método | `MultiPac::callTimbrarCFDI` |
| Endpoint | `MULTIPAC_WSDL_PROD` o `MULTIPAC_WSDL_DEV`; defaults HTTPS TimbradorXpress |
| Transporte/operación | SOAP `timbrarConSello` |
| Credencial | `MULTIPAC_APIKEY_PROD` o `MULTIPAC_APIKEY_DEV` |
| Parámetros exactos | `apikey`, `xmlCFDI`, `keyPEM` |
| Respuesta tolerada | objeto con variantes `code/message/data|xml|cfdi/uuid/pdf/acuse`; o string |
| Éxito real aplicado | existencia de XML timbrado no vacío; no se exige código concreto |
| UUID | campo de respuesta o extracción de `tfd:TimbreFiscalDigital/@UUID` |
| SoapFault | wrapper devuelve `__getLastResponse()` como string; controlador lo convierte en excepción truncada |
| Persistencia | XML, UUID, PDF/acuse opcional, solicitud y datos relacionales |

Fecha de timbrado, sello CFD y sello SAT están dentro del XML timbrado; no se observan columnas dedicadas escritas por el flujo. Se considera timbrado al recibir XML no vacío, antes de persistirlo. Este orden permite un “timbre huérfano” si después falla PDF/persistencia/folio.

## 9. Generación de PDF

**PDF PAC/WSTools33:** `MultiPac::generatePDFV33` llama SOAP `generarPDF(usuario, claveAcceso, xmlB64, plantilla, json, logo)` a un WSDL HTTP literal. `FacturasController::generarPdfBase64DesdePacV33` usa plantilla `factura`; complementos usan `generarPdfBase64ComplementoPagos2` y plantilla `pagos2`. XML y JSON se codifican Base64; logo proviene de `public/uploads` y se codifica Base64. Se espera código `210` o PDF no vacío, según normalización del controlador.

**PDF local/fallback:** facturas usan `generarPdfBase64FallbackDompdf` con `facturas.pdf`. Complementos definen `generarPdfBase64FallbackDompdfComplemento`, pero el `catch` de `timbrar` comprueba/llama otro nombre (`generarPdfBase64FallbackDompdf`), que no existe en ese controlador: el fallback durante timbrado de pagos no se ejecuta. La regeneración de complementos sí usa su método específico.

**PDF regenerado:** las rutas `regenerar-pdf` leen el XML timbrado ya persistido, vuelven a intentar WSTools33 y después fallback local; actualizan únicamente `pdf`. No retimbran.

Riesgos: WSDL PDF sin TLS, credenciales literales, servicio externo adicional, respuesta SOAP heterogénea, `generatePDFV33` captura el último response desde `$clientTools` aunque el cliente usado es `$clientToolsV33`, posible propiedad no inicializada.

## 10. Persistencia

El repositorio sólo incluye una migración fiscal parcial (`add_total_to_facturas_table`); el resto del DDL es externo/legado. Tipos siguientes son inferidos por uso, no confirmados por migración.

| Tabla/campo | Contenido/formato observado |
|---|---|
| `facturas` | receptor/dirección, `estatus`, `xml` texto, `pdf` Base64 texto, `acuse` texto XML o respuesta, `solicitud_timbre` XML texto, `uuid`, tipo, comentarios, fechas, descuento y columnas opcionales serie/folio/pagos/totales |
| `factura_detalles` | conceptos, cantidad/precio/importe, IVA, claves SAT |
| `facturas_impuestos` | impuesto SAT, tipo, tasa, monto |
| `complementos` | cabecera, XML, PDF Base64, acuse, solicitud, UUID, estatus, serie/folio y total según columnas disponibles |
| `complementos_pagos` | documentos relacionados, parcialidad, saldos, importe y datos de pago/impuestos |
| `folios` | usuario, tipo, serie y contador variable |
| `users` | `timbres_disponibles` |
| `users_perfil` / `users_info_factura` | emisor, régimen, CP, CSD password/metadatos |
| `users_info_factura_documentos` | tipo, nombre/ruta y metadatos de CER/KEY |
| `clientes` | receptor y correo |
| `clave_prod_serv`, `clave_unidad`, `productos` | catálogos/productos |
| `informacion` | fallback legado para emisor en complementos |

No se observan columnas dedicadas para sello, sello SAT, no. certificado, fecha de timbrado, error PAC, motivo de cancelación, UUID sustituto, estado SAT, fecha de correo ni número de intentos.

## 11. Descargas y vistas

`facturaOrFail` y `complementoOrFail` filtran por `users_id`; las descargas heredan autenticación.

- XML: devuelve texto persistido, `Content-Type: application/xml; charset=UTF-8`, `Content-Disposition: attachment`; 404 si vacío.
- PDF: `base64_decode(..., true)`, `Content-Type: application/pdf`, attachment; 404/error si falta o Base64 inválido.
- Acuse de factura: texto, XML attachment con nombre `Cancelado {serie+folio} - {uuid}.xml`; 404 si falta.
- Complementos no tienen ruta para descargar acuse.
- Preview: HTML Blade desde payload de sesión, no es un PDF.
- Vista invoice: parsea XML y combina con detalles/tablas; si faltan artefactos muestra/aborta según método.

Los nombres se derivan de serie, folio y UUID. No se encontró sanitización explícita del nombre más allá de datos fiscales esperados.

## 12. Envío por correo

**Flujo activo:** no existe ruta, controlador, Mailable, job, reintento ni campo de seguimiento para enviar CFDI por correo.

**Legado:** `MultiPac::generarFacturaWhitData` contiene `Mail::send('emails.facturacion.factura_generada', ...)` y adjuntos XML/PDF; no se encontró la vista de email, y el método depende de clases/modelos ausentes y no es llamado por rutas actuales. Además, la asignación de `$attachments` reemplaza el arreglo al agregar XML, por lo que probablemente pierde el PDF.

La configuración SMTP genérica de Laravel usa `MAIL_*` y `MAIL_FROM_*`, pero no demuestra envío fiscal. En el flujo actual un fallo de PDF no revierte el timbrado; un fallo de correo no puede ocurrir porque no se envía.

## 13. Cancelación

`FacturasController::cancelar` y `ComplementosController::cancelar`:

1. Validan motivo en `01..04` y exigen sustitución para `01` (un mensaje de factura dice erróneamente “motivo 04”).
2. Cargan documento del usuario y rechazan `CANCELADA`.
3. Extraen del XML UUID, RFC emisor/receptor y total exacto.
4. Cargan KEY PEM y construyen CER PEM desde certificado Base64.
5. Llaman `MultiPac::callCancelarPEM`.

Contrato SOAP: `cancelarPEM(apikey, keyPEM, cerPEM, uuid, rfcEmisor, rfcReceptor, total, motivo, folioSustitucion)`. Se interpreta éxito si `status=success` o `code=0`; el acuse se toma de `data|acuse|ACUSE`. Luego una transacción actualiza `estatus=CANCELADA`, conserva/guarda acuse y consume un timbre. En complementos también revierte/ajusta efectos en `complementos_pagos`.

Motivos: `01` comprobante con errores con relación (requiere UUID sustituto); `02` errores sin relación; `03` operación no realizada; `04` operación nominativa en factura global. FC2 sólo valida el requisito de sustitución para `01`; no implementa reglas adicionales.

No distingue solicitud aceptada, cancelación pendiente de aceptación, rechazada, cancelada sin aceptación ni expiración. Marca cancelado inmediatamente con la respuesta inicial del PAC.

## 14. Acuses de cancelación

El PAC puede devolver acuse en `data`, `acuse` o `ACUSE`. FC2 lo trata como string sin normalizar si es XML o Base64 y lo guarda en la columna `acuse`. La descarga de factura lo entrega como XML sin decodificar. Complementos lo guardan, pero no exponen descarga.

Si el PAC reporta éxito sin acuse, se conserva el acuse anterior o queda vacío y aun así se marca `CANCELADA`. No hay regeneración/consulta de acuse. Los únicos criterios de éxito codificados son `status=success` o `code=0`; no hay catálogo de estados SAT intermedios.

## 15. Consulta de estatus

No se encontró consulta activa SAT/PAC, ruta manual, servicio, comando, job ni scheduler para actualizar estatus. `MultiPac::callConsultarAutorizacionesPendientes` es legado: usa `$clientNomina` no inicializado, credenciales también no inicializadas y pasa `PrivateKeyPem` dos veces; no está invocado.

Por ello, `estatus` refleja decisiones locales (`TIMBRADA`, `CANCELADA`), no una reconciliación periódica con SAT. `CsdStatus` consulta vigencia local del certificado, no estatus de CFDI.

## 16. Regeneraciones y reintentos

| Acción | Disponible | Idempotencia/riesgo |
|---|---|---|
| Regenerar PDF | Sí, factura y complemento | Segura respecto al timbre; sobrescribe Base64 |
| Reenviar correo | No | — |
| Retimbrar | No como acción explícita; repetir POST sí vuelve a llamar PAC | Riesgo crítico de duplicidad/timbre huérfano |
| Reintentar cancelación | Repetir POST mientras BD no diga CANCELADA | Sin request-id; puede duplicar consumo/solicitud |
| Consultar acuse/estatus | No | Estado local puede quedar desfasado |

No hay llave idempotente, estado `TIMBRANDO`, UUID/serie-folio único confirmado, outbox, tabla de intentos ni bloqueo previo al PAC. Un doble clic o timeout del navegador puede emitir dos llamadas. Las transacciones empiezan después del timbrado remoto y no pueden revertir al PAC.

## 17. Credenciales y configuración

| Nombre | Uso | Estado |
|---|---|---|
| `MULTIPAC_MODE` | Selecciona `prod`/`dev` | Usada; no aparece en `.env.example` |
| `MULTIPAC_WSDL_PROD`, `MULTIPAC_WSDL_DEV` | WSDL timbrado/cancelación | Usadas; defaults en código |
| `MULTIPAC_APIKEY_PROD`, `MULTIPAC_APIKEY_DEV` | API key PAC | Secretos esperados; no aparecen en `.env.example` |
| `usuarioTools`, `passwordTools` | WSTools33 PDF/sello legado | Valores hardcodeados en `MultiPac::__construct`; no reproducidos aquí |
| `users_info_factura.password` | Contraseña CSD | Persistida por `uploadCsd`; cifrado no observado |
| CER/KEY/PEM y sus rutas | Timbrado/cancelación | BD + `public/uploads` |
| `MAIL_*`, `MAIL_FROM_*` | Correo Laravel genérico | No usados por flujo fiscal activo |

No puede confirmarse que TimbradorXpress/MultiPac, FacturaLoPlus/WSTools33 y TotalNot pertenezcan a la misma cuenta o proveedor. Técnicamente usan credenciales y endpoints diferentes; no deben asumirse equivalentes.

## 18. Ambientes

`MULTIPAC_MODE=prod` elige WSDL/API key de producción; cualquier otro valor usa desarrollo. Si falta mode, depende de `APP_ENV=production`. Timbrado y cancelación comparten esta selección.

PDF no selecciona ambiente: siempre usa el WSDL WSTools33 HTTP literal y las mismas credenciales hardcodeadas. El validador CSD tampoco muestra selección de ambiente. Riesgos: probar contra producción por `APP_ENV`, combinar XML timbrado dev con PDF común, usar CSD real en validación externa, o desplegar con cache de configuración mientras `env()` se llama directamente desde una clase.

## 19. Manejo de errores

| Operación/código | Origen | Comportamiento actual | Riesgo / recomendación |
|---|---|---|---|
| Timbrado, string/SoapFault | `callTimbrarCFDI` | Devuelve raw response y luego excepción visible truncada | Puede filtrar SOAP/XML; registrar correlación y mensaje redactado |
| Timbrado sin XML | controlador | Rechaza aun sin código | Correcto como guardia, pero persistir intento antes |
| PDF `210` | WSTools33/diccionario | Considerado éxito; lee PDF Base64 | Validar firma `%PDF`, tamaño y respuesta tipada |
| PDF fallo | WSTools33 | Factura cae a Dompdf; pago puede quedar sin PDF | Corregir fallback y procesar asíncrono |
| Cancelación `0` o `success` | PAC | Marca `CANCELADA` | No basta para estados SAT; reconciliar |
| Otros códigos PAC | `timbradorxpress_errors.php` | `traducirCodigoPac` busca por operación/código | Catálogo parcial y posiblemente desalineado |
| Upload/validación CSD | REST/OpenSSL | `try/catch`, mensajes y borrado parcial | TLS deshabilitado; no exponer comando/password |
| Persistencia posterior al PAC | DB | Excepción y retorno al formulario | Timbre remoto huérfano; usar durable orchestration |
| Complementos | logs Laravel | Guarda mensaje y trace | Trace/respuesta puede incluir datos fiscales |

`config/timbradorxpress_errors.php` contiene códigos agrupados para timbrado, cancelación y generación PDF. Es un catálogo de presentación, no prueba del contrato vigente. Debe versionarse contra documentación del PAC.

## 20. Seguridad

- **Crítico:** `usuarioTools` y `passwordTools` hardcodeados en `MultiPac::__construct`.
- **Crítico:** WSTools33 usa HTTP sin TLS y transporta credenciales, XML, JSON y logo.
- **Crítico:** `ConfiguracionController::validarCsd` usa `Http::withoutVerifying()` al enviar RFC, contraseña, CER y KEY.
- **Alto:** CER/KEY/PEM se guardan bajo `public/uploads`; aunque no haya ruta explícita, están bajo webroot.
- **Alto:** contraseña CSD persistida aparentemente en claro.
- **Alto:** SOAP `trace=true` conserva request/response y los wrappers pueden devolver respuesta cruda.
- **Alto:** errores PAC se muestran en flash y complementos registran mensaje/trace.
- **Medio:** XML fiscal completo reside en BD y potencialmente logs/respuestas; contiene datos personales/fiscales.
- **Medio:** nombres/rutas de archivos se resuelven mediante varias heurísticas legacy.
- **Medio:** upload valida extensión/tamaño, pero la verificación material depende del servicio inseguro y OpenSSL.
- **Positivo:** rutas fiscales autenticadas/verificadas, CSRF predeterminado y filtrado de propiedad por usuario en documentos.

## 21. Problemas técnicos encontrados

| Severidad | Archivo/método | Evidencia e impacto | Recomendación |
|---|---|---|---|
| Crítico | `MultiPac::__construct` | Credenciales PDF literales y endpoint HTTP | Revocar/rotar; secret manager; HTTPS |
| Crítico | `ConfiguracionController::validarCsd` | TLS deshabilitado y envío de llave/password | Validación local o servicio con TLS/pinning y mínimo dato |
| Crítico | `FacturasController::timbrar`, `ComplementosController::timbrar` | PAC antes de registro durable/idempotente | Intento persistido + idempotency key + reconciliación |
| Alto | almacenamiento CSD | KEY/PEM en `public/uploads` | Storage privado cifrado, permisos mínimos |
| Alto | `users_info_factura.password` | Se guarda contraseña sin cifrado observable | No persistir o cifrar con KMS |
| Alto | `cancelar` ambos | Éxito inicial equivale a cancelado definitivo | Máquina de estados y consulta SAT/PAC |
| Alto | `ComplementosController::timbrar` | Nombre de fallback Dompdf inexistente | Servicio PDF separado y pruebas |
| Alto | esquema | Casi todo DDL fiscal falta | Migraciones basales y diccionario de datos |
| Alto | ambos controladores | Sin idempotencia; doble POST | Restricciones únicas y token por operación |
| Medio | `MultiPac::generatePDFV33` | Catch consulta cliente equivocado/no inicializado | Cliente único tipado |
| Medio | facturas | Cálculos con `float` | Decimales enteros/BCMath y política de redondeo |
| Medio | `routes/web.php` | Ruta a controlador inexistente y APIs duplicadas | Limpiar mapa de rutas |
| Medio | logs/errores | Trace y respuesta cruda potencialmente sensibles | Redacción estructurada |
| Bajo | mensaje de `cancelar` factura | Para motivo `01` dice `04` | Corregir texto |
| Bajo | comentarios/código duplicado | Dos implementaciones PAC/CSD/PDF | Extraer adaptadores y casos de uso |

## 22. Qué debemos copiar a iKontrol

| Comportamiento FC2 | Reutilizar | Rediseñar / no copiar | Motivo |
|---|---|---|---|
| Nodos CFDI 4.0 y Pagos 2.0 | Como referencia funcional | Encapsular y validar XSD/reglas | Buen mapa, no motor certificado |
| Helpers decimales de Pagos | Sí, con pruebas | Generalizar a todo CFDI | Mejor que floats |
| Timbrado `timbrarConSello` | Contrato/adaptador | Orquestación durable | PAC externo no es transacción DB |
| PDF separado del timbre | Sí | Job/retry independiente | No debe bloquear persistencia fiscal |
| Regenerar PDF desde XML | Sí | Servicio idempotente | No requiere retimbrar |
| Cancelación con motivos | Contrato base | Estados/acuse/consulta completos | Respuesta inicial no siempre es final |
| Persistir XML/UUID | Sí | Hacerlo inmediatamente/durable | Son artefactos fiscales primarios |
| Base64 PDF en BD | Sólo compatibilidad | Object storage privado + hash/ruta | Tamaño, backups y memoria |
| CSD en webroot | No | Vault/storage cifrado | Exposición crítica |
| Secretos hardcodeados | No | Secret manager | Rotación/auditoría |
| Correo dentro del flujo | No | Outbox/job con adjuntos | Reintentos sin retimbrar |
| Catálogo de errores | Sí como semilla | Versionar contra PAC | Puede estar obsoleto |

## 23. Flujo recomendado para iKontrol

**Hecho observado en FC2:** XML -> PAC -> PDF -> transacción local.  
**Inferencia:** ante timeout o fallo DB puede existir CFDI válido no registrado localmente.  
**Recomendación:**

```text
Documento VALIDADO
 -> intento fiscal durable (idempotency_key, serie/folio reservados)
 -> XML canónico + hash persistidos
 -> sello/timbrado por adaptador PAC
 -> persistencia inmediata y atómica de UUID + XML + respuesta mínima
 -> estado TIMBRADO
 -> job PDF independiente (PAC o renderer local)
 -> job correo/outbox independiente
 -> solicitud de cancelación durable con motivo/sustitución
 -> guardar acuse/respuesta y estado PENDIENTE/RECHAZADA/CANCELADA
 -> job de consulta/reconciliación SAT/PAC
```

Separar `DocumentBuilder`, `CsdVault`, `PacStampingGateway`, `FiscalDocumentRepository`, `PdfRenderer`, `MailOutbox`, `CancellationService` y `StatusReconciler`. Usar importes decimales exactos, restricciones únicas por emisor/serie/folio y UUID, hashes de XML/PDF, auditoría sin secretos y estados explícitos.

## 24. Datos faltantes

- DDL/migraciones de `facturas`, detalles, impuestos, complementos, pagos, folios y tablas de configuración.
- Documentación contractual/WSDL congelado y respuestas reales de TimbradorXpress/WSTools33/validador.
- Confirmación de códigos de éxito y formato de acuse.
- Confirmación de si `data` de cancelación implica solicitud o cancelación final.
- Evidencia de ambientes/credenciales desplegados; `.env.example` no enumera MultiPac.
- Índices únicos, constraints, tipos/tamaños reales y cifrado de columnas.
- Consulta SAT/PAC, aceptación de cancelación y recuperación de acuses: no encontrados.
- Correo fiscal activo y plantilla email: no encontrados.
- Nómina/retenciones/complementos adicionales: no implementados.
- Pruebas fiscales automatizadas: no encontradas.
- `MultiPac::generarFacturaWhitData` y métodos de nómina/sello: aparentemente muertos/legado.
- `persistirFacturaCompletaDesdeTimbrado`: implementación alternativa sin llamada localizada en el flujo principal.

No se verificaron servicios externos ni datos productivos por restricción expresa.

## 25. Anexos

### A. Archivos revisados

- Rutas: `routes/web.php`, `routes/api.php`, `routes/console.php`.
- Controladores: `FacturasController`, `ComplementosController`, `ConfiguracionController`, `FoliosController`, `ClientesController`, `ProductosController`, `ReportesController`, `DashboardController`, `Api\SeriesController`, `Api\SatController`, `Api\ProductosApiController`.
- PAC: `Traits/PacMultiPacTrait.php`, `Extensions/MultiPac/MultiPac.php`.
- Modelos/soporte: `Factura`, `FacturaDetalle`, `FacturaImpuesto`, `Folio`, `Cliente`, `Producto`, `ClaveProdServ`, `ClaveUnidad`, `User`, `CsdStatus`.
- Configuración: `sat.php`, `timbradorxpress_errors.php`, `mail.php`, `services.php`, `filesystems.php`, `logging.php`, `database.php`, `.env.example`.
- BD/comandos: todas las migraciones, `BackfillFacturasTotalCommand`, `Console\Kernel`.
- Vistas: todos los archivos bajo `resources/views/facturas`, `resources/views/documentos/complementos`, `configuracion`, `folios`, `clientes`, `productos`; búsqueda global de correo/adjuntos.
- Dependencias/documentación: `composer.json`, `composer.lock`, `README.md`, `docs/CONTEXTO_FISCAL_FACTUCARE2.md`.

### B. Métodos fiscales revisados

`create`, `preview`, `previewGet`, `renderPreviewFromPayload`, `timbrar`, `generarXmlCfdi40DesdePayload`, `generarXmlPagos20DesdePayload`, normalizadores/cálculos/impuestos, `cargarCsdParaTimbrado`, `inyectarCertificadoEnXml`, `timbrarConPacMultipac`, `callTimbrarCFDI`, `guardarFacturaTimbrada`, `guardarComplementoTimbrado`, folios/timbres, parsers XML, descargas, `regenerarPdf`, generadores/fallback PDF, `cancelar`, `callCancelarPEM`, `uploadCsd`, `validarCsd`, conversiones PEM y comando backfill.

### C. Tablas fiscales localizadas

`facturas`, `factura_detalles`, `facturas_impuestos`, `complementos`, `complementos_pagos`, `folios`, `users`, `clientes`, `productos`, `clave_prod_serv`, `clave_unidad`, `users_perfil`, `users_info_factura`, `users_info_factura_documentos`, `informacion`.

### D. Operaciones SOAP/REST y códigos

| Operación | Estado |
|---|---|
| SOAP `timbrarConSello` | Activa |
| SOAP `cancelarPEM` | Activa |
| SOAP WSTools33 `generarPDF` | Activa |
| SOAP `generarSello` | Legado/no invocado |
| `ConsultarAutorizacionesPendientes` | Legado/no funcional confirmado |
| REST validación CSD | Activa al upload |
| Códigos | PDF `210`; cancelación `0`/`success`; demás en `timbradorxpress_errors.php` |

### E. Variables de entorno

Fiscales: `MULTIPAC_MODE`, `MULTIPAC_WSDL_PROD`, `MULTIPAC_WSDL_DEV`, `MULTIPAC_APIKEY_PROD`, `MULTIPAC_APIKEY_DEV`. Relacionadas: `APP_ENV`, `APP_DEBUG`, `APP_URL`, `DB_*`, `MAIL_*`, `MAIL_FROM_*`, `FILESYSTEM_DISK`, `LOG_*`, `QUEUE_CONNECTION`, `SESSION_DRIVER`. Las cinco `MULTIPAC_*` no están documentadas en `.env.example`.

### F. Inventario final solicitado

1. **Archivos revisados:** inventario A; 269 archivos fueron enumerados y el contenido fiscal se inspeccionó por archivo/búsqueda.
2. **Controladores fiscales:** Facturas, Complementos, Configuración y Folios; APIs SAT/series/productos como apoyo.
3. **Servicios fiscales:** `PacMultiPacTrait`, `MultiPac`, validador CSD y Dompdf.
4. **Modelos fiscales:** Factura, FacturaDetalle, FacturaImpuesto, Folio; no hay modelos de complemento.
5. **Rutas fiscales:** tabla de la sección 4.
6. **Tablas fiscales:** anexo C.
7. **Operaciones PAC:** timbrarConSello y cancelarPEM activas.
8. **Operaciones PDF:** generarPDF WSTools33, fallback Dompdf y regeneración.
9. **Cancelación:** factura y complemento por cancelarPEM.
10. **Consulta:** ninguna activa; una función legacy no alcanzable.
11. **Acuses:** columna/descarga en factura; columna sin descarga en complemento.
12. **Variables:** anexo E.
13. **Credenciales hardcodeadas:** usuario y password WSTools33 en `MultiPac::__construct`; valores omitidos.
14. **Flujos confirmados:** CFDI I/E/T genérico y Pagos 2.0; PDF, descargas, regeneración, cancelación local.
15. **Flujos incompletos:** correo, consulta/reconciliación, aceptación de cancelación, acuse de complemento, borrador durable.
16. **Código muerto/legado:** CFDI 3.3 `generarFacturaWhitData`, sello separado, nómina/autorizaciones, ruta rows inválida y persistencia alternativa no llamada.
17. **Riesgos críticos:** secretos PDF, HTTP PDF, validación CSD sin TLS e idempotencia/persistencia posterior al PAC.
18. **Riesgos altos:** CSD webroot/password, cancelación sin estados, fallback Pagos, DDL ausente.
19. **Riesgos medios:** floats, SOAP crudo/logs, ruta duplicada y catch PDF defectuoso.
20. **Recomendaciones iKontrol:** sección 23 y matriz de sección 22.

### G. Estado y garantías de esta revisión

El `git status --short --untracked-files=all` final es:

```text
?? docs/CONTEXTO_FISCAL_FACTUCARE2.md
?? docs/FC2_MAPA_COMPLETO_FLUJO_FISCAL.md
```

`docs/CONTEXTO_FISCAL_FACTUCARE2.md` ya existía sin seguimiento al iniciar. Esta revisión agregó únicamente `docs/FC2_MAPA_COMPLETO_FLUJO_FISCAL.md`.

- Código de aplicación modificado: **no**.
- Migraciones/BD ejecutadas o modificadas: **no**.
- Llamadas externas/PAC/SAT: **no**.
- Documentos creados/timbrados/cancelados: **no**.
- Commit: **no**.
- Push: **no**.
