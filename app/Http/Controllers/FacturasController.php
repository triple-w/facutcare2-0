<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Extensions\MultiPac\MultiPac;


class FacturasController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $perPage = 300;
        $q = trim((string) $request->get('q', ''));

        $facturas = DB::table('facturas as f')
            ->where('f.users_id', $userId)
            ->select([
                'f.*',
                DB::raw('(SELECT COUNT(*) FROM factura_detalles d WHERE d.users_facturas_id = f.id) as detalles_count'),
            ])
            ->orderByDesc('f.id')
            ->paginate($perPage)
            ->withQueryString();

        // ✅ AQUÍ ESTABA EL FALTANTE: hidratar la página actual del paginator
        $items = $facturas->getCollection();              // Collection de stdClass
        $items = $this->hidratarFacturasDesdeXml($items); // regresa Collection también
        $facturas->setCollection($items);

        // ✅ Si es AJAX, regresamos solo filas + meta
        if ($request->ajax()) {
            $rowsHtml = view('facturas.partials.rows', compact('facturas'))->render();

            return response()->json([
                'rows_html' => $rowsHtml,
                'meta' => [
                    'current_page' => $facturas->currentPage(),
                    'last_page'    => $facturas->lastPage(),
                    'total'        => $facturas->total(),
                    'per_page'     => $facturas->perPage(),
                    'count'        => $facturas->count(),
                ],
            ]);
        }

        return view('facturas.index', compact('facturas', 'q'));
    }



    public function indexChunk(Request $request)
    {
        $userId = auth()->id();

        $limit  = min(300, max(1, (int)$request->query('limit', 300)));
        $offset = max(0, (int)$request->query('offset', 0));

        $query = DB::table('facturas as f')
            ->where('f.users_id', $userId)
            ->select(['f.*'])
            ->orderByDesc('f.id');

        $rows = $query->skip($offset)->take($limit)->get();
        $facturas = $this->hidratarFacturasDesdeXml($rows);

        $html = view('facturas._rows', compact('facturas'))->render();

        return response()->json([
            'html' => $html,
            'next_offset' => $offset + $limit,
            'count' => count($facturas),
        ]);
    }

    private function hidratarFacturasDesdeXml($rows)
    {
        // Acepta Collection o array/iterable
        if ($rows instanceof \Illuminate\Support\Collection) {
            $items = $rows;
        } else {
            $items = collect($rows);
        }

        foreach ($items as $f) {
            $needs = empty($f->serie) || empty($f->folio) || empty($f->total) || empty($f->fecha) || empty($f->uuid);

            if ($needs && !empty($f->xml)) {
                try {
                    $xml = (string)$f->xml;

                    // (Opcional pero útil) si tu BD guarda el xml en base64 en algunos casos:
                    // si no parece xml, intenta base64_decode.
                    if (strpos($xml, '<') === false) {
                        $dec = base64_decode($xml, true);
                        if ($dec !== false && strpos($dec, '<') !== false) {
                            $xml = $dec;
                        }
                    }

                    $meta = $this->parseCfdiBasicsFromXml($xml);

                    if (empty($f->serie) && !empty($meta['serie'])) $f->serie = $meta['serie'];
                    if (empty($f->folio) && !empty($meta['folio'])) $f->folio = $meta['folio'];

                    if ((empty($f->total) || (float)$f->total <= 0) && isset($meta['total'])) {
                        $f->total = (float)$meta['total'];
                    }

                    if (empty($f->fecha) && !empty($meta['fecha'])) $f->fecha = $meta['fecha'];
                    if (empty($f->uuid)  && !empty($meta['uuid']))  $f->uuid  = $meta['uuid'];

                } catch (\Throwable $e) {
                    // no romper listado
                }
            }
        }

        return $items;
    }





    private function parseCfdiBasicsFromXml(string $xmlString): array
    {
        $out = [
            'serie' => '',
            'folio' => '',
            'total' => 0,
            'fecha' => '',
            'uuid'  => '',
        ];

        $xmlString = trim($xmlString);
        if ($xmlString === '') return $out;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlString, LIBXML_NONET)) {
            return $out;
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('tfd',   'http://www.sat.gob.mx/TimbreFiscalDigital');

        // CFDI 4.0 o 3.3
        $comp = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);
        if ($comp) {
            $out['serie'] = $comp->getAttribute('Serie') ?: $comp->getAttribute('serie');
            $out['folio'] = $comp->getAttribute('Folio') ?: $comp->getAttribute('folio');

            $totalRaw = $comp->getAttribute('Total') ?: $comp->getAttribute('total');
            $totalRaw = str_replace([',', ' '], '', (string)$totalRaw);
            $out['total'] = (float)$totalRaw;

            $out['fecha'] = $comp->getAttribute('Fecha') ?: $comp->getAttribute('fecha');
        }

        // UUID (TimbreFiscalDigital)
        $tfd = $xp->query('//tfd:TimbreFiscalDigital')->item(0);
        if ($tfd) {
            $out['uuid'] = $tfd->getAttribute('UUID') ?: $tfd->getAttribute('uuid');
        }

        return $out;
    }


    private function getRfcActivo(): ?string
    {
        // 1) Si en FC2 ya guardas RFC activo en sesión (como iKontrol), úsalo
        $rfc = session('rfc_activo_rfc') ?? session('rfc_activo') ?? null;

        // 2) Fallback “FactuCare clásico”: username suele ser el RFC
        if (!$rfc) {
            $rfc = auth()->user()->username ?? null;
        }

        return $rfc;
    }

    public function create(Request $request)
    {
        $userId = auth()->id();
        $rfcActivo = $this->getRfcActivo();

        // Clientes
        $clientes = DB::table('clientes')
            ->where('users_id', $userId)
            ->orderBy('razon_social')
            ->get();

        // Folios (FC1)
        $folios = collect();
        if (Schema::hasTable('folios')) {
            $folios = DB::table('folios')
                ->where('users_id', $userId)
                ->orderBy('id', 'desc')
                ->get();
        }

        // Ventana SAT (como tu blade iKontrol lo maneja)
        $minFecha = now()->copy()->subHours(72)->format('Y-m-d\TH:i');
        $maxFecha = now()->format('Y-m-d\TH:i');

        // Para que el blade pinte la variable JS
        $prefill = session('factura_draft', []);

        // Si tu blade usa rfcUsuarioId (id interno), manda userId por ahora
        $rfcUsuarioId = (int)($userId);

        return view('facturas.create', [
            'prefill' => $prefill,
            'clientes' => $clientes,
            'folios' => $folios,
            'rfcActivo' => $rfcActivo,
            'rfcUsuarioId' => $rfcUsuarioId,
            'minFecha' => $minFecha,
            'maxFecha' => $maxFecha,
        ]);
    }

    public function nueva()
    {
        session()->forget('factura_draft');
        return redirect()->route('facturas.create');
    }

    public function preview(Request $request)
{
    $userId = auth()->id();
    if (!$userId) {
        return redirect()->route('login');
    }

    $payload = json_decode((string)$request->input('payload', ''), true);

    if (!is_array($payload) || empty($payload)) {
        return redirect()->route('facturas.create')
            ->with('error', 'Payload inválido.');
    }

    // ✅ folio normalizado
    $payload = $this->normalizarFolioEnPayload($userId, $payload);

    // ✅ debug ya con payload válido
    $payload['_debug_timbrado_paths'] = $this->debugTimbradoPaths($userId);

    // guarda el draft para volver a editar / timbrar
    session()->put('factura_draft', $payload);

    return $this->renderPreviewFromPayload($payload);
}

    private function debugTimbradoPaths(int $userId): array
    {
        $tablaDocs = 'users_info_factura_documentos';
    $tablaInfo = 'users_info_factura';

    if (!Schema::hasTable($tablaInfo) || !Schema::hasTable($tablaDocs)) {
        return [
            'error' => 'No existen las tablas users_info_factura o users_info_factura_documentos.',
            'paths' => [
                'base_path' => base_path(),
                'public_path' => public_path(),
                'storage_path' => storage_path(),
                'cwd' => getcwd(),
            ],
        ];
    }

    // 1) Encontrar el registro de users_info_factura por users_id
    $info = DB::table($tablaInfo)
        ->where('users_id', $userId)
        ->first();

    if (!$info || !isset($info->id)) {
        return [
            'error' => "No encontré users_info_factura para users_id={$userId}.",
            'user_id' => $userId,
            'paths' => [
                'base_path' => base_path(),
                'public_path' => public_path(),
                'storage_path' => storage_path(),
                'cwd' => getcwd(),
            ],
        ];
    }

    $usersFacturaInfoId = (int)$info->id;

    // 2) Traer documentos por users_factura_info_id
    $docs = DB::table($tablaDocs)
        ->where('users_factura_info_id', $usersFacturaInfoId)
        ->get();

    $out = [
        'user_id' => $userId,
        'users_factura_info_id' => $usersFacturaInfoId,
        'docs_count' => count($docs),
        'docs' => [],
        'paths' => [
            'base_path' => base_path(),
            'public_path' => public_path(),
            'storage_path' => storage_path(),
            'cwd' => getcwd(),
        ],
    ];

    foreach ($docs as $d) {
        $tipo   = (string)($d->tipo ?? '');
        $name   = (string)($d->_name ?? '');
        $pathDb = (string)($d->_path ?? '');

        // candidato PEM (tu lógica vieja: si es .key => .key.pem)
        $pemName = $name;
        $ext = strtolower(pathinfo($pemName, PATHINFO_EXTENSION));
        if ($ext === 'key') {
            $pemName .= '.pem';
        }

        // candidatos: muchos proyectos guardan uploads en public/
        $candidatePublic      = public_path(trim($pathDb, "/\\") . '/' . $name);
        $candidatePublicPem   = public_path(trim($pathDb, "/\\") . '/' . $pemName);

        // candidatos: otros los guardan en storage/app/
        $candidateStorage     = storage_path('app/' . trim($pathDb, "/\\") . '/' . $name);
        $candidateStoragePem  = storage_path('app/' . trim($pathDb, "/\\") . '/' . $pemName);

        // candidatos: ruta directa desde base_path
        $candidateBase        = base_path(trim($pathDb, "/\\") . '/' . $name);
        $candidateBasePem     = base_path(trim($pathDb, "/\\") . '/' . $pemName);

        $out['docs'][] = [
            'tipo' => $tipo,
            '_name' => $name,
            '_path' => $pathDb,
            'numero_certificado' => (string)($d->numero_certificado ?? ''),
            'vigencia' => (string)($d->vigencia ?? ''),

            'candidates' => [
                'public'  => $candidatePublic,
                'storage' => $candidateStorage,
                'base'    => $candidateBase,
            ],
            'exists' => [
                'public'  => file_exists($candidatePublic),
                'storage' => file_exists($candidateStorage),
                'base'    => file_exists($candidateBase),
            ],

            'pem_name_guess' => $pemName,
            'pem_candidates' => [
                'public'  => $candidatePublicPem,
                'storage' => $candidateStoragePem,
                'base'    => $candidateBasePem,
            ],
            'pem_exists' => [
                'public'  => file_exists($candidatePublicPem),
                'storage' => file_exists($candidateStoragePem),
                'base'    => file_exists($candidateBasePem),
            ],
        ];
    }

    return $out;
    }

    /**
     * Resuelve la ruta REAL del documento:
     * 1) Si viene _path y existe en disco, úsalo tal cual.
     * 2) Si no, intenta en public/uploads/users_documentos/_name.
     */
    private function resolveUsersDocumentoPath(object $doc): string
    {
        $p = (string)($doc->_path ?? '');
        if ($p !== '' && is_file($p)) {
            return $p;
        }

        $name = (string)($doc->_name ?? '');
        if ($name === '') {
            throw new \RuntimeException('Documento sin _name ni _path resoluble.');
        }

        $fallback = public_path('uploads/users_documentos' . DIRECTORY_SEPARATOR . $name);
        if (is_file($fallback)) {
            return $fallback;
        }

        // Último intento: si _path venía pero no existía, lo devolvemos para que el error muestre esa ruta
        return $p !== '' ? $p : $fallback;
    }


    private function normalizarFolioEnPayload(int $userId, array $payload): array
    {
        // Si ya viene folio_id válido, no hacemos nada
        if (!empty($payload['folio_id']) && (int)$payload['folio_id'] > 0) {
            return $payload;
        }

        $serie = trim((string)($payload['serie'] ?? ''));
        if ($serie === '') {
            // Sin serie no podemos resolver folio_id
            return $payload;
        }

        // La tabla folios en FC1 tiene: id, users_id, tipo, serie, folio
        // Como tu payload trae tipo_comprobante (I/E/T...), aquí NO hay match directo con "tipo".
        // Entonces resolvemos por users_id + serie (y si quieres, luego refinamos por tipo).
        $folioRow = \DB::table('folios')
            ->where('users_id', $userId)
            ->where('serie', $serie)
            ->orderByDesc('id')
            ->first();

        if (!$folioRow) {
            // No existe folio configurado para esa serie
            return $payload;
        }

        // Inyectamos el ID real del folio
        $payload['folio_id'] = (int)$folioRow->id;

        // OPCIONAL: si quieres forzar que el folio usado sea el actual del sistema (recomendado)
        // para evitar que el front mande uno atrasado
        $payload['folio'] = (string)($folioRow->folio ?? ($payload['folio'] ?? ''));

        return $payload;
    }


    private function timbrarConPacMultipac(int $userId, string $xmlOriginal): array
    {
        // 1) Cargar CSD (cert b64 + key pem)
        $csd = $this->cargarCsdParaTimbrado($userId);

        $certB64 = (string)($csd['cert_b64'] ?? '');
        $noCert  = (string)($csd['no_certificado'] ?? '');
        $keyPem  = (string)($csd['key_pem'] ?? '');

        if ($certB64 === '' || $noCert === '' || $keyPem === '') {
            throw new \RuntimeException('CSD incompleto: falta cert_b64 / no_certificado / key_pem.');
        }

        // 2) Inyectar Certificado/NoCertificado/Sello vacío antes de enviar al PAC
        $xmlParaPac = $this->inyectarCertificadoEnXml($xmlOriginal, $certB64, $noCert);

        // 3) Timbrar (SOAP) - este método NO garantiza PDF
        $mp = new \App\Extensions\MultiPac\MultiPac(); // ajusta el namespace si tu clase está en otro
        $r = $mp->callTimbrarCFDI([
            'xmlCFDI' => $xmlParaPac,
            'keyPEM'  => $keyPem,
        ]);

        // Normalización "tolerante"
        if (is_string($r)) {
            throw new \RuntimeException('PAC (respuesta): ' . mb_substr($r, 0, 800));
        }

        $code    = (string)($r->code ?? $r->CODIGO ?? $r->codigo ?? '');
        $mensaje = (string)($r->message ?? $r->MENSAJE ?? $r->mensaje ?? '');

        // En SOAP de TimbradorXpress típicamente viene: code, message, data
        // y data trae el XML timbrado (en operaciones timbrar/timbrarConSello)
        $xmlTimbrado = (string)($r->data ?? $r->xml ?? $r->XML ?? $r->cfdi ?? $r->CFDI ?? '');

        if (trim($xmlTimbrado) === '') {
            // Si no hay XML, es error real
            $txt = $mensaje !== '' ? $mensaje : 'Sin mensaje del PAC.';
            if ($code !== '') $txt = "PAC error ({$code}): " . $txt;
            throw new \RuntimeException($txt);
        }

        // Extraer UUID del TimbreFiscalDigital si el PAC no lo da separado
        $uuid = (string)($r->uuid ?? $r->UUID ?? '');
        if ($uuid === '') {
            $uuid = $this->extraerUuidDeXml($xmlTimbrado); // asegúrate de tener este helper
        }

        // PDF: en timbrarConSello NO viene. Lo dejamos null y NO tronamos.
        $pdfB64 = (string)($r->pdf ?? $r->PDF ?? $r->pdfB64 ?? $r->PDFB64 ?? '');
        $pdfB64 = trim($pdfB64) !== '' ? $pdfB64 : null;

        $acuse = (string)($r->acuse ?? $r->ACUSE ?? '');

        return [
            'xml'     => $xmlTimbrado,
            'uuid'    => $uuid,
            'pdf'     => $pdfB64,       // puede ser null en este flujo
            'acuse'   => $acuse !== '' ? $acuse : null,
            'mensaje' => $mensaje !== '' ? $mensaje : null,
            'code'    => $code !== '' ? $code : null,
        ];
    }


    private function extraerUuidDeXml(string $xml): string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');

        if (!$dom->loadXML($xml)) {
            $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
            if (!$dom->loadXML($xml2)) {
                return '';
            }
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        /** @var \DOMElement|null $t */
        $t = $xp->query('//tfd:TimbreFiscalDigital')->item(0);

        if ($t instanceof \DOMElement) {
            return (string) $t->getAttribute('UUID');
        }

        return '';
    }






   public function timbrar(\Illuminate\Http\Request $request)
    {
        $userId = auth()->id();

        // Modo: debug = mostrar XML, timbrar = flujo real
        $modo = (string) $request->input('modo', 'timbrar');

        // Payload: si no viene en POST, lo tomamos del draft
        $payload = $request->input('payload');
        if (!$payload) {
            $payload = session('factura_draft', []);
        }

        if (!is_array($payload)) {
            $payload = (array) json_decode((string)$payload, true);
        }

        if (empty($payload)) {
            return back()->with('error', 'No hay datos de factura en sesión. Regresa a crear la factura.');
        }

        // Normaliza folio en payload (ya lo tienes funcionando)
        $payload = $this->normalizarFolioEnPayload($userId, $payload);
        session(['factura_draft' => $payload]); // útil para consistencia

        // 1) Generar XML base
        $xmlOriginal = $this->generarXmlCfdi40DesdePayload($payload);

        if ($modo === 'debug') {
            return response($xmlOriginal, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // 2) Cliente
        $clienteId = (int)($payload['cliente_id'] ?? 0);
        $cliente = \DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return back()->with('error', 'Cliente inválido.');
        }

        try {
            // 3) Timbrar con PAC (regresa XML timbrado y UUID)
            $resp = $this->timbrarConPacMultipac($userId, $xmlOriginal);

            $xmlTimbrado = (string)($resp['xml'] ?? '');
            $uuid        = (string)($resp['uuid'] ?? '');
            $acuseXml    = isset($resp['acuse']) ? (string)$resp['acuse'] : null;

            if (trim($xmlTimbrado) === '') {
                $msg = (string)($resp['mensaje'] ?? 'PAC no devolvió XML timbrado.');
                throw new \RuntimeException($msg);
            }

            // 3.1) GENERAR PDF (base64) usando el XML timbrado (como FC1)
            //      Si el PAC no lo puede generar, hacemos fallback a Dompdf si existe.
            $pdfB64 = '';
            try {
                $pdfB64 = $this->generarPdfBase64DesdePacV33($userId, $xmlTimbrado, $payload, $cliente);
            } catch (\Throwable $e) {
                // Fallback: Dompdf (si lo tienes)
                $pdfB64 = $this->generarPdfBase64FallbackDompdf($xmlTimbrado);
                // Si ni fallback pudo, NO tronamos el timbrado, pero quedará sin PDF hasta regenerar.
                // Puedes cambiar esto a throw si lo quieres “obligatorio”.
            }

            // 4) Guardar + Avanzar folio + Consumir timbre (atómico)
            $facturaId = \DB::transaction(function () use (
                $userId, $payload, $cliente, $xmlOriginal, $xmlTimbrado, $uuid, $pdfB64, $acuseXml
            ) {
                $facturaId = $this->guardarFacturaTimbrada(
                    $userId,
                    $payload,
                    $cliente,
                    $xmlOriginal,
                    $xmlTimbrado,
                    $uuid,
                    $pdfB64,
                    $acuseXml
                );

                // SOLO si se guardó la factura, avanzamos folio y consumimos timbre
                $folioId = (int)($payload['folio_id'] ?? 0);
                $this->avanzarFolioYConsumirTimbre($userId, $folioId);

                return (int)$facturaId;
            });

            // 5) Limpia draft y redirige al invoice con mensaje
            session()->forget('factura_draft');

            return redirect()
                ->route('facturas.ver', $facturaId)
                ->with('success', 'Factura generada correctamente.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Error al timbrar: ' . $e->getMessage());
        }
    }




    private function inyectarCertificadoEnXml(string $xml, string $certB64, string $noCertificado): string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml)) {
            $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
            if (!$dom->loadXML($xml2)) {
                throw new \RuntimeException('No se pudo cargar XML para inyectar certificado.');
            }
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

        /** @var \DOMElement|null $comprobante */
        $comprobante = $xp->query('/cfdi:Comprobante')->item(0);
        if (!$comprobante) {
            throw new \RuntimeException('XML inválido: no existe cfdi:Comprobante.');
        }

        $comprobante->setAttribute('Certificado', $certB64);
        $comprobante->setAttribute('NoCertificado', $noCertificado);

        if (!$comprobante->hasAttribute('Sello')) {
            $comprobante->setAttribute('Sello', '');
        } else {
            // lo dejamos vacío para que el PAC selle
            $comprobante->setAttribute('Sello', '');
        }

        $dom->formatOutput = true;
        return $dom->saveXML();
    }



    private function guardarFacturaTimbrada(
        int $userId,
        array $payload,
        object $cliente,
        string $xmlOriginal,
        string $xmlTimbrado,
        string $uuid,
        ?string $pdfB64,
        ?string $acuse
    ): int {
        // Totales server-side (igual que preview)
        $conceptos = $payload['conceptos'] ?? [];
        if (!is_array($conceptos)) $conceptos = [];

        $subtotal = 0.0;
        $ivaTotal = 0.0;
        $descuentoTotal = 0.0;

        foreach ($conceptos as $c) {
            $cantidad = (float)($c['cantidad'] ?? 0);
            $precio   = (float)($c['precio'] ?? 0);
            $desc     = (float)($c['descuento'] ?? 0);

            $importe = max(0, ($cantidad * $precio) - $desc);
            $subtotal += $importe;
            $descuentoTotal += $desc;

            $aplicaIva = (bool)($c['aplica_iva'] ?? true);
            $tasaIva   = (float)($c['iva_tasa'] ?? 0.16);
            $iva       = $aplicaIva ? ($importe * $tasaIva) : 0.0;
            $ivaTotal += $iva;
        }

        $total = max(0, ($subtotal - $descuentoTotal) + $ivaTotal);

        // Fecha factura
        $fechaFactura = (string)($payload['fecha'] ?? '');
        $fechaFactura = $fechaFactura ? date('Y-m-d H:i:s', strtotime($fechaFactura)) : date('Y-m-d H:i:s');

        // Insert cabecera (mínimo requerido por tu tabla)
        $insert = [
            'users_id' => $userId,

            // datos del receptor
            'rfc' => (string)($cliente->rfc ?? ''),
            'razon_social' => (string)($cliente->razon_social ?? ''),
            'calle' => (string)($cliente->calle ?? ''),
            'no_ext' => (string)($cliente->no_ext ?? ''),
            'no_int' => (string)($cliente->no_int ?? ''),
            'colonia' => (string)($cliente->colonia ?? ''),
            'municipio' => (string)($cliente->municipio ?? ''),
            'localidad' => (string)($cliente->localidad ?? ''),
            'estado' => (string)($cliente->estado ?? ''),
            'codigo_postal' => (string)($cliente->codigo_postal ?? ''),
            'pais' => (string)($cliente->pais ?? 'MEX'),
            'telefono' => (string)($cliente->telefono ?? ''),
            'nombre_contacto' => (string)($cliente->nombre_contacto ?? ''),

            'estatus' => 'TIMBRADA',
            'id_cancelar' => null,
            'fecha' => date('Y-m-d H:i:s'),

            'xml' => $xmlTimbrado,
            'pdf' => $pdfB64 ?: '',
            'acuse' => $acuse,
            'solicitud_timbre' => $xmlOriginal,

            'uuid' => $uuid,
            'nombre_comprobante' => 'Factura',
            'tipo_comprobante' => $this->mapTipoComprobanteTexto((string)($payload['tipo_comprobante'] ?? 'I')),
            'comentarios_pdf' => (string)($payload['comentarios_pdf'] ?? null),
            'fecha_factura' => $fechaFactura,

            // OJO: descuento en tu tabla existe, lo llenamos con total descuento calculado
            'descuento' => $descuentoTotal,
        ];

        // Campos opcionales, solo si existen en tu tabla facturas
        $tabla = 'facturas';
        $opcionales = [
            'serie' => (string)($payload['serie'] ?? ''),
            'folio' => (string)($payload['folio'] ?? ''),
            'forma_pago' => (string)($payload['forma_pago'] ?? ''),
            'metodo_pago' => (string)($payload['metodo_pago'] ?? ''),
            'uso_cfdi' => (string)($payload['uso_cfdi'] ?? ''),
            'moneda' => (string)($payload['moneda'] ?? 'MXN'),
            'subtotal' => $subtotal,
            'iva' => $ivaTotal,
            'total' => $total,
            'lugar_expedicion' => (string)($payload['lugar_expedicion'] ?? ''),
        ];

        foreach ($opcionales as $col => $val) {
            if (\Schema::hasColumn($tabla, $col)) {
                $insert[$col] = $val;
            }
        }

        $facturaId = \DB::table('facturas')->insertGetId($insert);

        // Detalles
        foreach ($conceptos as $c) {
            $cantidad = (float)($c['cantidad'] ?? 1);
            $precio   = (float)($c['precio'] ?? 0);
            $desc     = (float)($c['descuento'] ?? 0);
            $importe  = max(0, ($cantidad * $precio) - $desc);

            $aplicaIva = (bool)($c['aplica_iva'] ?? true);
            $tasaIva   = (float)($c['iva_tasa'] ?? 0.16);
            $iva       = $aplicaIva ? ($importe * $tasaIva) : 0.0;

            \DB::table('factura_detalles')->insert([
                'users_facturas_id' => $facturaId,
                'clave' => (string)($c['no_identificacion'] ?? ($c['clave'] ?? 'N/A')),
                'unidad' => (string)($c['unidad'] ?? 'SERV'),
                'precio' => $precio,
                'cantidad' => $cantidad, // mejor float (si tu columna es int, MySQL lo truncará)
                'importe' => $importe,
                'descripcion' => (string)($c['descripcion'] ?? ''),
                'desglosado' => $aplicaIva ? 1 : 0,
                'observaciones' => null,
                'nuevoPrecio' => $precio,
                'iva' => $iva,
                'numero_clave_prod' => (string)($c['clave_prod_serv'] ?? ''),
                'numero_clave_unidad' => (string)($c['clave_unidad'] ?? ''),
            ]);
        }

        // Impuestos globales (mínimo)
        if ($ivaTotal > 0) {
            \DB::table('facturas_impuestos')->insert([
                'users_facturas_id' => $facturaId,
                'impuesto' => '002',
                'tipo' => 'TRAS',
                'tasa' => 16,
                'monto' => $ivaTotal,
            ]);
        }

        return $facturaId;
    }


    private function mapTipoComprobanteTexto(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));

        return match ($tipo) {
            'I' => 'INGRESO',
            'E' => 'EGRESO',
            'T' => 'TRASLADO',
            'P' => 'PAGO',
            'N' => 'NOMINA',
            default => 'INGRESO',
        };
    }




    public function previewGet()
    {
        $payload = session('factura_draft', []);

        if (!is_array($payload) || empty($payload)) {
            return redirect()->route('facturas.create')
                ->with('error', 'No hay borrador en sesión. Crea una factura primero.');
        }

        return $this->renderPreviewFromPayload($payload);
    }

    private function renderPreviewFromPayload(array $payload)
    {
        $userId = auth()->id();

        $clienteId = (int)($payload['cliente_id'] ?? 0);

        $cliente = \DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return redirect()->route('facturas.create')
                ->with('error', 'Cliente inválido o no pertenece al usuario.');
        }

        $conceptos = $payload['conceptos'] ?? [];
        if (!is_array($conceptos)) $conceptos = [];

        $subtotal = 0.0;
        $iva = 0.0;
        $descuento = 0.0;

        $conceptosLimpios = [];

        foreach ($conceptos as $c) {
            $cantidad = (float)($c['cantidad'] ?? 0);
            $precio   = (float)($c['precio'] ?? 0);
            $desc     = (float)($c['descuento'] ?? 0);

            $importe = max(0, ($cantidad * $precio) - $desc);
            $subtotal += $importe;
            $descuento += $desc;

            $aplicaIva = (bool)($c['aplica_iva'] ?? true);
            $tasaIva   = (float)($c['iva_tasa'] ?? 0.16);

            $ivaConcepto = $aplicaIva ? ($importe * $tasaIva) : 0;
            $iva += $ivaConcepto;

            $conceptosLimpios[] = [
                'cantidad' => $cantidad,
                'unidad' => (string)($c['unidad'] ?? 'SERV'),
                'descripcion' => (string)($c['descripcion'] ?? ''),
                'clave_prod_serv' => (string)($c['clave_prod_serv'] ?? ''),
                'clave_unidad' => (string)($c['clave_unidad'] ?? ''),
                'precio' => $precio,
                'descuento' => $desc,
                'importe' => $importe,
                'aplica_iva' => $aplicaIva,
                'iva_tasa' => $tasaIva,
                'iva' => $ivaConcepto,
            ];
        }

        $total = max(0, ($subtotal - $descuento) + $iva);

        $comprobante = [
            'rfc_activo' => (string)($payload['rfc_activo'] ?? ''),
            'folio_id' => (int)($payload['folio_id'] ?? 0),

            'tipo_comprobante' => (string)($payload['tipo_comprobante'] ?? 'I'),
            'serie' => (string)($payload['serie'] ?? ''),
            'folio' => (string)($payload['folio'] ?? ''),
            'fecha' => (string)($payload['fecha'] ?? ''),

            'metodo_pago' => (string)($payload['metodo_pago'] ?? 'PUE'),
            'forma_pago' => (string)($payload['forma_pago'] ?? '99'),
            'uso_cfdi' => (string)($payload['uso_cfdi'] ?? ''),
            'exportacion' => (string)($payload['exportacion'] ?? '01'),
            'moneda' => (string)($payload['moneda'] ?? 'MXN'),

            'descuento' => $descuento,
            'comentarios_pdf' => (string)($payload['comentarios_pdf'] ?? ''),
        ];

        $totales = [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
        ];

        return view('facturas.preview', [
            'cliente' => $cliente,
            'conceptos' => $conceptosLimpios,
            'comprobante' => $comprobante,
            'totales' => $totales,
        ]);
    }

    private function resolveUsersDocumentoPathFromRow(object $doc): string
    {
        $p = trim((string)($doc->_path ?? ''));

        // si viene absoluto y existe, úsalo
        if ($p !== '' && is_file($p)) {
            return $p;
        }

        // si viene relativo, conviértelo a public_path
        if ($p !== '' && ($p[0] !== '/' && !preg_match('/^[A-Za-z]:\\\\/', $p))) {
            $cand = public_path(ltrim($p, "/\\"));
            if (is_file($cand)) return $cand;
        }

        // fallback por nombre en public/uploads/users_documentos
        $name = trim((string)($doc->_name ?? ''));
        if ($name !== '') {
            $fallback = public_path('uploads/users_documentos/' . ltrim($name, "/\\"));
            return $fallback;
        }

        return $p;
    }

    private function resolveKeyPemFromKeyPath(string $keyPath): string
    {
        $keyPath = trim($keyPath);
        if ($keyPath === '') return $keyPath;

        if (preg_match('/\.pem$/i', $keyPath)) return $keyPath;

        // 1) archivo.key.pem (tu caso real)
        $cand1 = $keyPath . '.pem';
        // 2) archivo.pem
        $cand2 = preg_replace('/\.key$/i', '.pem', $keyPath);

        if (is_file($cand1)) return $cand1;
        if (is_file($cand2)) return $cand2;

        return $cand1;
    }




    private function cargarCsdParaTimbrado(int $userId): array
    {
        $info = DB::table('users_info_factura')->where('users_id', $userId)->first();
        if (!$info) {
            throw new \RuntimeException('El usuario no tiene users_info_factura.');
        }

        $docs = DB::table('users_info_factura_documentos')
            ->where('users_factura_info_id', $info->id)
            ->where('validado', 1)
            ->get();

        if ($docs->isEmpty()) {
            throw new \RuntimeException('No hay documentos validados en users_info_factura_documentos.');
        }

        // ----------------------------
        // Helpers locales (solo aquí)
        // ----------------------------
        $normalizePath = function (string $p): string {
            $p = trim($p);
            if ($p === '') return '';

            // absoluto Linux (/...) o Windows (C:\...)
            if ($p[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $p)) {
                return $p;
            }

            // relativo -> public_path()
            return public_path(ltrim($p, "/\\"));
        };

        $resolveDocFile = function (object $doc) use ($normalizePath): string {
            $pathDb = trim((string)($doc->_path ?? ''));
            $name   = trim((string)($doc->_name ?? ''));

            // 1) Si _path es archivo directo y existe, úsalo
            if ($pathDb !== '') {
                $p = $normalizePath($pathDb);
                if (is_file($p)) {
                    return $p;
                }

                // 2) Si _path es carpeta/base, intenta _path + _name (sin duplicar)
                if ($name !== '') {
                    // si _path ya termina con el nombre, NO lo vuelvas a pegar
                    if (basename($p) !== $name) {
                        $cand = rtrim($p, "/\\") . DIRECTORY_SEPARATOR . $name;
                        if (is_file($cand)) return $cand;
                    }
                }

                // si no existe, regresamos el normalizado para mostrar ruta real en error
                return $p;
            }

            // 3) Sin _path: fallback clásico en public/uploads/users_documentos/<name>
            if ($name === '') {
                return '';
            }

            return public_path('uploads/users_documentos' . DIRECTORY_SEPARATOR . $name);
        };

        $resolveKeyPemPath = function (string $keyPath): string {
            $keyPath = trim($keyPath);
            if ($keyPath === '') return $keyPath;

            // si ya es PEM, úsalo
            if (preg_match('/\.pem$/i', $keyPath)) {
                return $keyPath;
            }

            // Caso real tuyo en server: archivo.key.pem
            $cand1 = $keyPath . '.pem';

            // Fallback: archivo.key -> archivo.pem
            $cand2 = preg_replace('/\.key$/i', '.pem', $keyPath);

            if (is_file($cand1)) return $cand1;
            if (is_file($cand2)) return $cand2;

            // si ninguno existe, regresa cand1 para error explícito
            return $cand1;
        };

        // ----------------------------
        // 1) Buscar CERT (.cer)
        // ----------------------------
        $cer = $docs->first(function ($d) {
            return ($d->tipo === 'ARCHIVO_CERTIFICADO') && str_ends_with(strtolower((string)$d->_name), '.cer');
        });

        if (!$cer) {
            throw new \RuntimeException('No se encontró ARCHIVO_CERTIFICADO .cer.');
        }

        // ----------------------------
        // 2) Buscar LLAVE (en BD normalmente es .key, NO .pem)
        //    Aquí YA NO pedimos que termine en .pem
        // ----------------------------
        $keyDoc = $docs->first(function ($d) {
            return ($d->tipo === 'ARCHIVO_LLAVE');
        });

        if (!$keyDoc) {
            throw new \RuntimeException('No se encontró ARCHIVO_LLAVE.');
        }

        // ----------------------------
        // 3) Resolver rutas reales (usa _path)
        // ----------------------------
        $cerPath = $resolveDocFile($cer);
        $keyPath = $resolveDocFile($keyDoc);          // puede ser .key
        $keyPemPath = $resolveKeyPemPath($keyPath);   // busca .key.pem

        if (!is_file($cerPath)) {
            throw new \RuntimeException("No existe el archivo .cer en: {$cerPath}");
        }
        if (!is_file($keyPemPath)) {
            throw new \RuntimeException("No existe el archivo .pem en: {$keyPemPath} (derivado de: {$keyPath})");
        }

        // ----------------------------
        // 4) Cargar contenido
        // ----------------------------
        $certB64 = base64_encode(file_get_contents($cerPath));
        $keyPem  = file_get_contents($keyPemPath);

        // Número certificado (de tu BD)
        $noCert = trim((string)($cer->numero_certificado ?? $cer->no_certificado ?? $cer->numero ?? ''));
        if ($noCert === '') {
            throw new \RuntimeException('El documento .cer no tiene numero_certificado en BD (users_info_factura_documentos).');
        }

        return [
            'cert_b64' => $certB64,
            'no_certificado' => $noCert,
            'key_pem' => $keyPem,
            'cer_path' => $cerPath,
            'key_pem_path' => $keyPemPath,
        ];
    }




private function adjuntarCertificadoAlXml(string $xml, string $certB64, string $noCertificado): string
{
    libxml_use_internal_errors(true);

    $dom = new \DOMDocument('1.0', 'UTF-8');
    if (!$dom->loadXML($xml)) {
        // intenta limpiar encoding
        $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
        $ok = $dom->loadXML($xml2);
        if (!$ok) {
            throw new \RuntimeException('XML inválido, no se pudo cargar en DOM.');
        }
    }

    $comprobante = $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', '*')->item(0);
    if (!$comprobante) {
        throw new \RuntimeException('No se encontró nodo cfdi:Comprobante.');
    }

    // Estos atributos normalmente son requeridos para el sellado/timbrado
    $comprobante->setAttribute('Certificado', $certB64);
    if ($noCertificado !== '') {
        $comprobante->setAttribute('NoCertificado', $noCertificado);
    }

    // Sello se lo dejaríamos vacío para que el PAC lo selle con keyPEM
    if (!$comprobante->hasAttribute('Sello')) {
        $comprobante->setAttribute('Sello', '');
    }

    $dom->formatOutput = true;
    return $dom->saveXML();
}

    private function generarXmlCfdi40DesdePayload(array $payload): string
    {
        $userId = auth()->id();

        // ===== 1) Cliente =====
        $clienteId = (int)($payload['cliente_id'] ?? 0);
        $cliente = \DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            throw new \RuntimeException('Cliente inválido.');
        }

        // ===== 2) Emisor (perfil) =====
        $perfil = \DB::table('users_perfil')->where('users_id', $userId)->first();
        if (!$perfil) {
            throw new \RuntimeException('No existe perfil de emisor (users_perfil).');
        }

        // ===== 3) Conceptos =====
        $conceptos = $payload['conceptos'] ?? [];
        if (!is_array($conceptos) || !count($conceptos)) {
            throw new \RuntimeException('No hay conceptos.');
        }

        // ===== Totales + acumuladores =====
        $subtotal = 0.0;
        $descuento = 0.0;

        // Acumuladores impuestos (para nodo global)
        $trasAgg = []; // key => ['impuesto','tipo_factor','tasa','importe']
        $retAgg  = [];

        // ===== DOM =====
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $cfdiNS = 'http://www.sat.gob.mx/cfd/4';
        $xsiNS  = 'http://www.w3.org/2001/XMLSchema-instance';

        $c = $dom->createElementNS($cfdiNS, 'cfdi:Comprobante');
        $dom->appendChild($c);

        // Namespaces
        $c->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $xsiNS);
        $c->setAttributeNS($xsiNS, 'xsi:schemaLocation',
            'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd'
        );

        // ===== Atributos Comprobante (más fieles) =====
        $serie = (string)($payload['serie'] ?? '');
        $folio = (string)($payload['folio'] ?? '');
        $fechaIn = (string)($payload['fecha'] ?? '');
        $fecha = $fechaIn ? date('Y-m-d\TH:i:s', strtotime($fechaIn)) : date('Y-m-d\TH:i:s');

        $tipoComprobante = (string)($payload['tipo_comprobante'] ?? 'I');
        $moneda = (string)($payload['moneda'] ?? 'MXN');
        $formaPago = (string)($payload['forma_pago'] ?? '99');
        $metodoPago = (string)($payload['metodo_pago'] ?? 'PUE');
        $usoCfdi = (string)($payload['uso_cfdi'] ?? '');
        $exportacion = (string)($payload['exportacion'] ?? '01');

        // Estos dos son los que te faltan en el XML “fiel”
        $condicionesPago = trim((string)($payload['condiciones_pago'] ?? ''));
        $tipoCambio = trim((string)($payload['tipo_cambio'] ?? ''));

        // Lugar expedición desde perfil
        $lugarExpedicion = (string)($perfil->codigo_postal ?? '');

        $c->setAttribute('Version', '4.0');
        if ($serie !== '') $c->setAttribute('Serie', $serie);
        if ($folio !== '') $c->setAttribute('Folio', $folio);
        $c->setAttribute('Fecha', $fecha);

        // OJO: en timbrado REAL van Sello/Certificado/NoCertificado.
        // Aquí aún NO los ponemos para no inventar valores.

        $c->setAttribute('Moneda', $moneda);
        $c->setAttribute('TipoDeComprobante', $tipoComprobante);
        $c->setAttribute('Exportacion', $exportacion);
        if ($lugarExpedicion !== '') $c->setAttribute('LugarExpedicion', $lugarExpedicion);

        // CFDI 4: TipoCambio se incluye si moneda != MXN (y si lo tienes)
        if (strtoupper($moneda) !== 'MXN') {
            if ($tipoCambio === '') {
                throw new \RuntimeException('Moneda distinta a MXN requiere TipoCambio.');
            }
            $c->setAttribute('TipoCambio', $tipoCambio);
        } elseif ($tipoCambio !== '') {
            // si quieres forzarlo siempre como en tu ejemplo:
            $c->setAttribute('TipoCambio', $tipoCambio);
        }

        if ($condicionesPago !== '') {
            $c->setAttribute('CondicionesDePago', $this->xmlClean($condicionesPago));
        }

        // FormaPago/MetodoPago (aplican normalmente excepto traslado)
        if ($tipoComprobante !== 'T') {
            $c->setAttribute('FormaPago', $formaPago);
            $c->setAttribute('MetodoPago', $metodoPago);
        }

        // ===== InformacionGlobal (Factura Global) =====
        // Regla: Si es Ingreso y Receptor RFC = XAXX010101000 y Nombre contiene "PUBLICO EN GENERAL",
        // entonces debe existir cfdi:InformacionGlobal.
        $receptorRfc = strtoupper(trim((string)($cliente->rfc ?? '')));
        $receptorNombre = strtoupper(trim((string)($cliente->razon_social ?? '')));

        $esPublicoEnGeneral = ($tipoComprobante === 'I')
            && ($receptorRfc === 'XAXX010101000')
            && (strpos($receptorNombre, 'PUBLICO EN GENERAL') !== false);

        if ($esPublicoEnGeneral) {
            // Puedes permitir que venga desde el payload en el futuro,
            // por ahora default diario (01) y mes/año de la fecha del comprobante.
            $ts = strtotime($fecha);
            $periodicidad = (string)($payload['informacion_global']['periodicidad'] ?? '01'); // 01=Diario
            $meses = (string)($payload['informacion_global']['meses'] ?? date('m', $ts));     // 01..12
            $anio  = (string)($payload['informacion_global']['anio']  ?? date('Y', $ts));

            $infoGlobal = $dom->createElementNS($cfdiNS, 'cfdi:InformacionGlobal');
            $infoGlobal->setAttribute('Periodicidad', $periodicidad);
            $infoGlobal->setAttribute('Meses', str_pad($meses, 2, '0', STR_PAD_LEFT));
            $infoGlobal->setAttribute('Año', $anio);

            // Lo insertamos ANTES de CfdiRelacionados/Emisor para respetar el orden del comprobante
            // (como lo hacías en el sistema viejo).
            $c->appendChild($infoGlobal);
        }


        // ===== CfdiRelacionados (si aplica) =====
        $rels = $payload['relacionados'] ?? [];
        if (is_array($rels) && count($rels)) {
            $byTipo = [];
            foreach ($rels as $r) {
                $tr = trim((string)($r['tipo_relacion'] ?? ''));
                $uuid = trim((string)($r['uuid'] ?? ''));
                if ($tr === '' || $uuid === '') continue;
                $byTipo[$tr][] = $uuid;
            }

            foreach ($byTipo as $tr => $uuids) {
                $cfdiRels = $dom->createElementNS($cfdiNS, 'cfdi:CfdiRelacionados');
                $cfdiRels->setAttribute('TipoRelacion', $tr);

                foreach ($uuids as $uuid) {
                    $rel = $dom->createElementNS($cfdiNS, 'cfdi:CfdiRelacionado');
                    $rel->setAttribute('UUID', $uuid);
                    $cfdiRels->appendChild($rel);
                }

                $c->appendChild($cfdiRels);
            }
        }

        // ===== Emisor =====
        $em = $dom->createElementNS($cfdiNS, 'cfdi:Emisor');
        $em->setAttribute('Rfc', (string)($perfil->rfc ?? ''));
        $em->setAttribute('Nombre', $this->xmlClean((string)($perfil->razon_social ?? '')));
        $regEmisor = (string)($perfil->numero_regimen33 ?? $perfil->numero_regimen ?? '');
        if ($regEmisor !== '') $em->setAttribute('RegimenFiscal', $regEmisor);
        $c->appendChild($em);

        // ===== Receptor =====
        $re = $dom->createElementNS($cfdiNS, 'cfdi:Receptor');
        $re->setAttribute('Rfc', (string)($cliente->rfc ?? ''));
        $re->setAttribute('Nombre', $this->xmlClean((string)($cliente->razon_social ?? '')));
        if ($usoCfdi !== '') $re->setAttribute('UsoCFDI', $usoCfdi);

        // Estos dos son obligatorios en CFDI 4
        $domFiscalRec = (string)($cliente->codigo_postal ?? $cliente->cp ?? '');
        if ($domFiscalRec === '') {
            throw new \RuntimeException('El receptor no tiene DomicilioFiscalReceptor (CP).');
        }
        $re->setAttribute('DomicilioFiscalReceptor', $domFiscalRec);

        $regRec = (string)($cliente->regimen_fiscal ?? $cliente->regimen_fiscal_receptor ?? '');
        if ($regRec === '') {
            throw new \RuntimeException('El receptor no tiene RegimenFiscalReceptor.');
        }
        $re->setAttribute('RegimenFiscalReceptor', $regRec);

        $c->appendChild($re);

        // ===== Conceptos =====
        $conceptosNode = $dom->createElementNS($cfdiNS, 'cfdi:Conceptos');

        foreach ($conceptos as $idx => $row) {
            $cantidad = (float)($row['cantidad'] ?? 0);
            $precio   = (float)($row['precio'] ?? 0);
            $desc     = (float)($row['descuento'] ?? 0);
            $base     = max(0, ($cantidad * $precio) - $desc);

            $subtotal += $base;
            $descuento += $desc;

            $concepto = $dom->createElementNS($cfdiNS, 'cfdi:Concepto');

            // “Fiel” al ejemplo:
            $concepto->setAttribute('Cantidad', $this->fmt($cantidad, 2));
            $concepto->setAttribute('ClaveProdServ', (string)($row['clave_prod_serv'] ?? '01010101'));
            $concepto->setAttribute('ClaveUnidad', (string)($row['clave_unidad'] ?? 'ACT'));
            $concepto->setAttribute('Unidad', $this->xmlClean((string)($row['unidad'] ?? 'SERV')));
            $concepto->setAttribute('Descripcion', $this->xmlClean((string)($row['descripcion'] ?? '')));
            $concepto->setAttribute('ValorUnitario', $this->fmt($precio, 2));
            $concepto->setAttribute('Importe', $this->fmt($base, 2));

            // NoIdentificacion (si existe en payload)
            $noIdent = trim((string)($row['no_identificacion'] ?? $row['clave'] ?? ''));
            if ($noIdent !== '') {
                $concepto->setAttribute('NoIdentificacion', $this->xmlClean($noIdent));
            }

            // Descuento (si aplica)
            if ($desc > 0) {
                $concepto->setAttribute('Descuento', $this->fmt($desc, 2));
            }

            // ===== Impuestos por concepto =====
            // 1) Si vienen impuestos “avanzados” (traslados/retenciones) en el payload
            //    respétalos, si no, usa IVA simple como fallback.
            $tieneImpuestos = false;
            $impuestosConceptoNode = null;
            $trasNode = null;
            $retNode  = null;

            $traslados = $row['traslados'] ?? null;
            $retenciones = $row['retenciones'] ?? null;

            if (is_array($traslados) || is_array($retenciones)) {
                $impuestosConceptoNode = $dom->createElementNS($cfdiNS, 'cfdi:Impuestos');

                // Retenciones
                if (is_array($retenciones) && count($retenciones)) {
                    $retNode = $dom->createElementNS($cfdiNS, 'cfdi:Retenciones');
                    foreach ($retenciones as $r) {
                        $impCode = (string)($r['impuesto'] ?? '');
                        $importe = (float)($r['importe'] ?? 0);

                        if ($impCode === '' || $importe <= 0) continue;

                        $ret = $dom->createElementNS($cfdiNS, 'cfdi:Retencion');
                        $ret->setAttribute('Impuesto', $impCode);
                        $ret->setAttribute('Importe', $this->fmt($importe, 2));
                        $retNode->appendChild($ret);

                        $key = $impCode;
                        $retAgg[$key] = $retAgg[$key] ?? ['impuesto'=>$impCode,'importe'=>0.0];
                        $retAgg[$key]['importe'] += $importe;

                        $tieneImpuestos = true;
                    }
                }

                // Traslados
                if (is_array($traslados) && count($traslados)) {
                    $trasNode = $dom->createElementNS($cfdiNS, 'cfdi:Traslados');
                    foreach ($traslados as $t) {
                        $impCode = (string)($t['impuesto'] ?? '002');
                        $factor  = (string)($t['tipo_factor'] ?? 'Tasa');
                        $tasa    = (float)($t['tasa'] ?? 0.16);

                        $tras = $dom->createElementNS($cfdiNS, 'cfdi:Traslado');
                        $tras->setAttribute('Base', $this->fmt($base, 2));
                        $tras->setAttribute('Impuesto', $impCode);
                        $tras->setAttribute('TipoFactor', $factor);

                        if (strtolower($factor) !== 'exento') {
                            $tras->setAttribute('TasaOCuota', $this->fmt($tasa, 6));
                            $importe = max(0, $base * $tasa);
                            $tras->setAttribute('Importe', $this->fmt($importe, 2));

                            $key = $impCode.'|'.$factor.'|'.$this->fmt($tasa, 6);
                            $trasAgg[$key] = $trasAgg[$key] ?? [
                                'impuesto'=>$impCode,'tipo_factor'=>$factor,'tasa'=>$this->fmt($tasa,6),'importe'=>0.0
                            ];
                            $trasAgg[$key]['importe'] += $importe;
                        }

                        $trasNode->appendChild($tras);
                        $tieneImpuestos = true;
                    }
                }
            } else {
                // 2) Fallback IVA simple (lo que ya traías)
                $aplicaIvaSimple = (bool)($row['aplica_iva'] ?? true);
                $ivaTasaSimple = (float)($row['iva_tasa'] ?? 0.16);

                if ($aplicaIvaSimple) {
                    $tieneImpuestos = true;

                    $impuestosConceptoNode = $dom->createElementNS($cfdiNS, 'cfdi:Impuestos');
                    $trasNode = $dom->createElementNS($cfdiNS, 'cfdi:Traslados');

                    $tras = $dom->createElementNS($cfdiNS, 'cfdi:Traslado');
                    $tras->setAttribute('Base', $this->fmt($base, 2));
                    $tras->setAttribute('Impuesto', '002');
                    $tras->setAttribute('TipoFactor', 'Tasa');
                    $tras->setAttribute('TasaOCuota', $this->fmt($ivaTasaSimple, 6));
                    $importe = max(0, $base * $ivaTasaSimple);
                    $tras->setAttribute('Importe', $this->fmt($importe, 2));

                    $trasNode->appendChild($tras);
                    $impuestosConceptoNode->appendChild($trasNode);

                    $key = '002|Tasa|'.$this->fmt($ivaTasaSimple, 6);
                    $trasAgg[$key] = $trasAgg[$key] ?? [
                        'impuesto'=>'002','tipo_factor'=>'Tasa','tasa'=>$this->fmt($ivaTasaSimple,6),'importe'=>0.0
                    ];
                    $trasAgg[$key]['importe'] += $importe;
                }
            }

            // ObjetoImp (01 sin impuestos, 02 con impuestos)
            $concepto->setAttribute('ObjetoImp', $tieneImpuestos ? '02' : '01');

            if ($impuestosConceptoNode) {
                if ($retNode)  $impuestosConceptoNode->appendChild($retNode);
                if ($trasNode) $impuestosConceptoNode->appendChild($trasNode);
                $concepto->appendChild($impuestosConceptoNode);
            }

            $conceptosNode->appendChild($concepto);
        }

        $c->appendChild($conceptosNode);

        // ===== Impuestos globales (como en tu ejemplo) =====
        $totalTras = 0.0;
        foreach ($trasAgg as $row) $totalTras += (float)$row['importe'];

        $totalRet = 0.0;
        foreach ($retAgg as $row) $totalRet += (float)$row['importe'];

        if ($totalTras > 0 || $totalRet > 0) {
            $impGlobal = $dom->createElementNS($cfdiNS, 'cfdi:Impuestos');

            if ($totalRet > 0) $impGlobal->setAttribute('TotalImpuestosRetenidos', $this->fmt($totalRet, 2));
            if ($totalTras > 0) $impGlobal->setAttribute('TotalImpuestosTrasladados', $this->fmt($totalTras, 2));

            if ($totalRet > 0) {
                $rets = $dom->createElementNS($cfdiNS, 'cfdi:Retenciones');
                foreach ($retAgg as $row) {
                    $r = $dom->createElementNS($cfdiNS, 'cfdi:Retencion');
                    $r->setAttribute('Impuesto', $row['impuesto']);
                    $r->setAttribute('Importe', $this->fmt($row['importe'], 2));
                    $rets->appendChild($r);
                }
                $impGlobal->appendChild($rets);
            }

            if ($totalTras > 0) {
                $tras = $dom->createElementNS($cfdiNS, 'cfdi:Traslados');
                foreach ($trasAgg as $row) {
                    $t = $dom->createElementNS($cfdiNS, 'cfdi:Traslado');
                    $t->setAttribute('Impuesto', $row['impuesto']);
                    $t->setAttribute('TipoFactor', $row['tipo_factor']);
                    if (strtolower($row['tipo_factor']) !== 'exento') {
                        $t->setAttribute('TasaOCuota', $row['tasa']);
                        $t->setAttribute('Importe', $this->fmt($row['importe'], 2));
                    }
                    // Base global (en CFDI 4 normalmente se incluye Base en cada Traslado global)
                    $t->setAttribute('Base', $this->fmt($subtotal - $descuento, 2));
                    $tras->appendChild($t);
                }
                $impGlobal->appendChild($tras);
            }

            $c->appendChild($impGlobal);
        }

        // ===== SubTotal/Descuento/Total =====
        $subTotalFinal = max(0, $subtotal);
        $descuentoFinal = max(0, $descuento);
        $totalFinal = max(0, ($subTotalFinal - $descuentoFinal) + $totalTras - $totalRet);

        $c->setAttribute('SubTotal', $this->fmt($subTotalFinal, 2));
        if ($descuentoFinal > 0) {
            $c->setAttribute('Descuento', $this->fmt($descuentoFinal, 2));
        }
        $c->setAttribute('Total', $this->fmt($totalFinal, 2));

        return $dom->saveXML();
    }


    private function fmt($n, int $decimals = 2): string
    {
        $n = (float)$n;
        return number_format($n, $decimals, '.', '');
    }

    private function xmlClean(string $s): string
    {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        // elimina caracteres de control no válidos en XML 1.0 (excepto tab, lf, cr)
        $s = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s);
        return trim($s);
    }

    private function mapImpuestoToSat(string $imp): string
    {
        $v = strtoupper(trim($imp));
        return match ($v) {
            'IVA', '002' => '002',
            'ISR', '001' => '001',
            'IEPS','003' => '003',
            default => preg_match('/^\d{3}$/', $v) ? $v : '002',
        };
    }


    /* ==========================
       Helpers
    ========================== */

    public function apiSeriesNext(Request $request)
    {
        $userId = auth()->id();

        $tipo = strtoupper((string)$request->get('tipo', 'I')); // I/E/T
        $folioId = (int)$request->get('folio_id', 0);

        // 1) Si mandan folio_id, úsalo directo
        if ($folioId > 0) {
            $f = DB::table('folios')
                ->where('users_id', $userId)
                ->where('id', $folioId)
                ->first();

            if ($f) {
                return response()->json([
                    'ok' => true,
                    'folio_id' => (int)$f->id,
                    'serie' => (string)$f->serie,
                    'folio' => (int)$f->folio,
                    'tipo' => (string)$f->tipo,
                ]);
            }
        }

        // 2) Si no hay folio_id, intenta encontrar por tipo
        // La columna tipo en FC1 varía (ingreso/egreso/traslado/factura/etc).
        $patterns = match ($tipo) {
            'E' => ['%egreso%', '%nota%', '%credito%', '%nc%'],
            'T' => ['%traslado%'],
            default => ['%fact%', '%ingreso%', '%factura%'],
        };

        $where = "LOWER(tipo) LIKE ? OR LOWER(tipo) LIKE ? OR LOWER(tipo) LIKE ? OR LOWER(tipo) LIKE ?";
        $params = array_pad($patterns, 4, $patterns[0]);

        $f = DB::table('folios')
            ->where('users_id', $userId)
            ->whereRaw($where, $params)
            ->orderBy('id', 'desc')
            ->first();

        // 3) fallback: el último folio del usuario
        if (!$f) {
            $f = DB::table('folios')
                ->where('users_id', $userId)
                ->orderBy('id', 'desc')
                ->first();
        }

        if (!$f) {
            return response()->json(['ok' => false, 'message' => 'No hay folios configurados.'], 404);
        }

        return response()->json([
            'ok' => true, 
            'folio_id' => (int)$f->id,
            'serie' => (string)$f->serie,
            'folio' => (int)$f->folio,
            'tipo' => (string)$f->tipo,
        ]);
    }

    public function apiProductosBuscar(Request $request)
    {
        $userId = auth()->id();
        $q = trim((string)$request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('productos')
            ->where('users_id', $userId)
            ->where(function ($qq) use ($q) {
                $qq->where('descripcion', 'like', "%{$q}%")
                ->orWhere('clave', 'like', "%{$q}%");
            })
            ->orderBy('descripcion')
            ->limit(15)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function apiSatProdServ(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('clave_prod_serv')
            ->where('clave', 'like', "{$q}%")
            ->orWhere('descripcion', 'like', "%{$q}%")
            ->limit(20)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function apiSatUnidad(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('clave_unidad')
            ->where('clave', 'like', "{$q}%")
            ->orWhere('descripcion', 'like', "%{$q}%")
            ->limit(20)
            ->get();

        return response()->json(['items' => $items]);
    }



    private function facturaOrFail(int $id): object
    {
        $userId = auth()->id();

        $factura = DB::table('facturas')
            ->where('id', $id)
            ->where('users_id', $userId)
            ->first();

        abort_if(!$factura, 404, 'Factura no encontrada');

        return $factura;
    }

    /**
     * Parser “básico” del CFDI desde el XML (para invoice y nombres).
     */
    private function parseCfdiBasics(string $xml): array
    {
        $out = [
            'serie' => null,
            'folio' => null,
            'fecha' => null,
            'subtotal' => null,
            'descuento' => null,
            'total' => null,
            'moneda' => null,
            'forma_pago' => null,
            'metodo_pago' => null,
            'tipo_comprobante' => null,
            'uuid' => null,
        ];

        if (trim($xml) === '') return $out;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok = $dom->loadXML($xml);

        if (!$ok) {
            $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
            $dom->loadXML($xml2);
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('tfd',  'http://www.sat.gob.mx/TimbreFiscalDigital');

        $c = $xp->query('//cfdi:Comprobante')->item(0);
        if ($c instanceof \DOMElement) {
            $out['serie'] = $c->getAttribute('Serie') ?: $c->getAttribute('serie') ?: null;
            $out['folio'] = $c->getAttribute('Folio') ?: $c->getAttribute('folio') ?: null;
            $out['fecha'] = $c->getAttribute('Fecha') ?: $c->getAttribute('fecha') ?: null;

            $out['subtotal'] = $c->getAttribute('SubTotal') ?: $c->getAttribute('subTotal') ?: $c->getAttribute('subtotal') ?: null;
            $out['descuento'] = $c->getAttribute('Descuento') ?: $c->getAttribute('descuento') ?: null;
            $out['total'] = $c->getAttribute('Total') ?: $c->getAttribute('total') ?: null;

            $out['moneda'] = $c->getAttribute('Moneda') ?: $c->getAttribute('moneda') ?: null;
            $out['forma_pago'] = $c->getAttribute('FormaPago') ?: $c->getAttribute('formaPago') ?: null;
            $out['metodo_pago'] = $c->getAttribute('MetodoPago') ?: $c->getAttribute('metodoPago') ?: null;
            $out['tipo_comprobante'] = $c->getAttribute('TipoDeComprobante') ?: $c->getAttribute('tipoDeComprobante') ?: null;
        }

        $t = $xp->query('//tfd:TimbreFiscalDigital')->item(0);
        if ($t instanceof \DOMElement) {
            $out['uuid'] = $t->getAttribute('UUID') ?: $t->getAttribute('Uuid') ?: $t->getAttribute('uuid') ?: null;
        }

        return $out;
    }

    /**
     * Normaliza IVA desde detalles si viene "escalado" (centavos/milésimas, etc.)
     * Si existe IVA objetivo (derivado del XML), intenta acercarse lo más posible.
     */
    private function normalizeIvaFromDetalles(float $ivaRaw, float $subtotal, ?float $ivaObjetivo = null): float
    {
        if ($ivaRaw <= 0) return 0.0;

        // Caso típico: IVA inflado brutal
        if ($subtotal > 0 && $ivaRaw <= $subtotal * 2) {
            return $ivaRaw;
        }

        $divisores = [1, 100, 1000, 10000, 1000000];
        $best = $ivaRaw;
        $bestScore = INF;

        foreach ($divisores as $d) {
            $v = $ivaRaw / $d;

            // Score: si hay objetivo, distancia al objetivo; si no hay, que no sea absurdo vs subtotal
            $score = $ivaObjetivo !== null
                ? abs($v - $ivaObjetivo)
                : ($subtotal > 0 ? max(0, $v - ($subtotal * 2)) : $v);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $v;
            }
        }

        return $best;
    }

    /* ==========================
       Acciones: XML / PDF / Acuse
    ========================== */

    public function downloadXml(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $xml = (string) ($factura->xml ?? '');
        abort_if(trim($xml) === '', 404, 'XML no disponible');

        $cfdi = $this->parseCfdiBasics($xml);
        $uuid = $cfdi['uuid'] ?: ($factura->uuid ?? $factura->id);

        $name = trim(($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? ''));
        if ($name === '') $name = 'Factura';

        $filename = "{$name} - {$uuid}.xml";

        return response($xml)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function downloadPdf(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $pdfB64 = (string) ($factura->pdf ?? '');
        abort_if(trim($pdfB64) === '', 404, 'PDF no disponible');

        $bin = base64_decode($pdfB64, true);
        if ($bin === false) {
            $bin = $pdfB64; // por si viniera binario
        }

        $xml = (string) ($factura->xml ?? '');
        $cfdi = $xml ? $this->parseCfdiBasics($xml) : [];
        $uuid = ($cfdi['uuid'] ?? null) ?: ($factura->uuid ?? $factura->id);

        $name = trim((($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? '')));
        if ($name === '') $name = 'Factura';

        $filename = "{$name} - {$uuid}.pdf";

        return response($bin)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function downloadAcuse(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $acuse = (string) ($factura->acuse ?? '');
        abort_if(trim($acuse) === '', 404, 'Acuse no disponible');

        $xml = (string) ($factura->xml ?? '');
        $cfdi = $xml ? $this->parseCfdiBasics($xml) : [];
        $uuid = ($cfdi['uuid'] ?? null) ?: ($factura->uuid ?? $factura->id);

        $name = trim((($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? '')));
        if ($name === '') $name = 'Factura';

        $filename = "Cancelado {$name} - {$uuid}.xml";

        return response($acuse)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /* ==========================
       Invoice (VER)
    ========================== */

    public function show(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $detalles = DB::table('factura_detalles')
            ->where('users_facturas_id', $factura->id)
            ->orderBy('id')
            ->get();

        $impuestos = DB::table('facturas_impuestos')
            ->where('users_facturas_id', $factura->id)
            ->orderBy('id')
            ->get();

        $xml = (string) ($factura->xml ?? '');
        $cfdi = $xml !== '' ? $this->parseCfdiBasics($xml) : [];

        // Subtotal / descuento / total desde XML si existen (son los más confiables)
        $subtotalXml  = is_numeric($cfdi['subtotal'] ?? null) ? (float)$cfdi['subtotal'] : null;
        $descuentoXml = is_numeric($cfdi['descuento'] ?? null) ? (float)$cfdi['descuento'] : null;
        $totalXml     = is_numeric($cfdi['total'] ?? null) ? (float)$cfdi['total'] : null;

        // Fallbacks DB
        $subtotalDb = (float)$detalles->sum('importe');
        $subtotal = $subtotalXml ?? $subtotalDb;

        $descuento = $descuentoXml ?? (float)($factura->descuento ?? 0);

        // Impuestos desde tabla (si existe)
        $impuestosTotal = (float)$impuestos->sum('monto');

        // IVA derivado del XML: Total - (SubTotal - Descuento)
        $ivaDerivadoXml = null;
        if ($totalXml !== null && $subtotalXml !== null) {
            $desc = $descuentoXml ?? 0.0;
            $ivaDerivadoXml = max(0, $totalXml - ($subtotalXml - $desc));
        }

        // IVA desde detalles (puede venir escalado)
        $ivaRawDetalles = (float)$detalles->sum('iva');
        $ivaDetalles = $this->normalizeIvaFromDetalles($ivaRawDetalles, $subtotal, $ivaDerivadoXml);

        // IVA final: preferimos tabla -> XML derivado -> detalles normalizados
        if ($impuestosTotal > 0) {
            $iva = $impuestosTotal;
        } elseif ($ivaDerivadoXml !== null) {
            $iva = $ivaDerivadoXml;
        } else {
            $iva = $ivaDetalles;
        }

        // Total final: preferimos XML, si no: subtotal - descuento + iva
        $total = $totalXml ?? max(0, ($subtotal - $descuento + $iva));

        $totales = [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
        ];

        // OJO: ya NO mandamos "emisor" porque dijiste que quieres quitar esa sección.
        return view('facturas.invoice', compact('factura', 'detalles', 'impuestos', 'cfdi', 'totales'));
    }

    /**
 * Extrae RFC/Nombre del receptor (y emisor si lo ocupas) desde el XML timbrado.
 */
private function parseCfdiParties(string $xml): array
    {
        $out = [
            'emisor_rfc' => null,
            'emisor_nombre' => null,
            'receptor_rfc' => null,
            'receptor_nombre' => null,
        ];

        if (trim($xml) === '') return $out;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml)) {
            $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
            $dom->loadXML($xml2);
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

        $e = $xp->query('//cfdi:Emisor')->item(0);
        if ($e instanceof \DOMElement) {
            $out['emisor_rfc'] = $e->getAttribute('Rfc') ?: $e->getAttribute('rfc') ?: null;
            $out['emisor_nombre'] = $e->getAttribute('Nombre') ?: $e->getAttribute('nombre') ?: null;
        }

        $r = $xp->query('//cfdi:Receptor')->item(0);
        if ($r instanceof \DOMElement) {
            $out['receptor_rfc'] = $r->getAttribute('Rfc') ?: $r->getAttribute('rfc') ?: null;
            $out['receptor_nombre'] = $r->getAttribute('Nombre') ?: $r->getAttribute('nombre') ?: null;
        }

        return $out;
    }

    /**
     * Guarda factura + detalles + impuestos. Regresa el ID de facturas.
     * IMPORTANTE: llena rfc/razon_social desde el XML timbrado para evitar “vacíos”.
     */
    private function persistirFacturaCompletaDesdeTimbrado(
        int $userId,
        array $payload,
        object $cliente,
        string $xmlTimbrado,
        string $pdfB64,
        ?string $acuseXml,
        string $uuid,
        string $mensajePac,
        int $folioId
    ): int {
        $meta = $this->parseCfdiBasics($xmlTimbrado);
        $parties = $this->parseCfdiParties($xmlTimbrado);

        $rfcReceptor = (string)($parties['receptor_rfc'] ?? $cliente->rfc ?? '');
        $razonReceptor = (string)($parties['receptor_nombre'] ?? $cliente->razon_social ?? '');

        // Fallback duro para que NO falle por NOT NULL
        if ($rfcReceptor === '') $rfcReceptor = (string)($cliente->rfc ?? 'XAXX010101000');
        if ($razonReceptor === '') $razonReceptor = (string)($cliente->razon_social ?? 'PUBLICO EN GENERAL');

        $now = now();

        // --- Serie y Folio (prioridad: XML timbrado -> payload) ---
        $serie = (string)($meta['serie'] ?? '');
        if ($serie === '') $serie = (string)($payload['serie'] ?? '');

        $folio = (string)($meta['folio'] ?? '');
        if ($folio === '') $folio = (string)($payload['folio'] ?? '');

        // --- Tipo comprobante con convención FactuCare (I -> INGRESO) ---
        $tipoRaw = (string)($payload['tipo_comprobante'] ?? ($meta['tipo'] ?? 'I'));
        $tipoTexto = $this->mapTipoComprobanteTexto($tipoRaw); // debe existir en el controller

        // 1) FACTURA (tabla facturas)
        $insert = [
            'users_id' => $userId,

            // Receptor
            'rfc' => $rfcReceptor,
            'razon_social' => $razonReceptor,
            'calle' => (string)($cliente->calle ?? ''),
            'no_ext' => (string)($cliente->no_ext ?? ''),
            'no_int' => (string)($cliente->no_int ?? null),
            'colonia' => (string)($cliente->colonia ?? ''),
            'municipio' => (string)($cliente->municipio ?? ''),
            'localidad' => (string)($cliente->localidad ?? null),
            'estado' => (string)($cliente->estado ?? ''),
            'codigo_postal' => (string)($cliente->codigo_postal ?? ''),
            'pais' => (string)($cliente->pais ?? 'MEXICO'),
            'telefono' => (string)($cliente->telefono ?? null),
            'nombre_contacto' => (string)($cliente->nombre_contacto ?? null),

            'estatus' => 'TIMBRADA',
            'fecha' => $now,

            // OJO: si quieres conservar "solicitud_timbre" como el XML original, aquí estás guardando mensaje.
            // Lo dejo como tú lo traes.
            'solicitud_timbre' => $mensajePac,

            'xml' => $xmlTimbrado,
            'uuid' => $uuid,
            'pdf' => $pdfB64,
            'acuse' => $acuseXml,

            'descuento' => (float)($payload['descuento'] ?? 0),

            // Nombre/tipo comprobante
            'nombre_comprobante' => 'Factura',
            'tipo_comprobante' => $tipoTexto, // ✅ INGRESO / EGRESO / etc.
            'comentarios_pdf' => (string)($payload['comentarios_pdf'] ?? ''),

            // fecha_factura: la del CFDI si viene, si no now()
            'fecha_factura' => !empty($meta['fecha']) ? $meta['fecha'] : $now,

            // id_cancelar default null
            'id_cancelar' => null,
        ];

        // Guardar serie/folio SOLO si existen columnas en tu tabla facturas
        if (\Schema::hasColumn('facturas', 'serie')) {
            $insert['serie'] = $serie;
        }
        if (\Schema::hasColumn('facturas', 'folio')) {
            $insert['folio'] = $folio;
        }

        $facturaId = DB::table('facturas')->insertGetId($insert);

        // 2) DETALLES (factura_detalles)
        $conceptos = $payload['conceptos'] ?? [];
        if (!is_array($conceptos)) $conceptos = [];

        foreach ($conceptos as $c) {
            $cantidad = (int)round((float)($c['cantidad'] ?? 0));
            if ($cantidad <= 0) $cantidad = 1;

            $precio = (float)($c['precio'] ?? 0);
            $desc = (float)($c['descuento'] ?? 0);

            $importe = max(0, ($cantidad * $precio) - $desc);

            $aplicaIva = (bool)($c['aplica_iva'] ?? true);
            $tasaIva = (float)($c['iva_tasa'] ?? 0.16);
            $iva = $aplicaIva ? ($importe * $tasaIva) : 0;

            DB::table('factura_detalles')->insert([
                'users_facturas_id' => $facturaId,
                'clave' => (string)($c['clave'] ?? $c['no_identificacion'] ?? ''),
                'unidad' => (string)($c['unidad'] ?? 'SERV'),
                'precio' => $precio,
                'cantidad' => $cantidad,
                'importe' => $importe,
                'descripcion' => (string)($c['descripcion'] ?? ''),
                'desglosado' => 1,
                'observaciones' => (string)($c['observaciones'] ?? null),
                'nuevoPrecio' => $precio,
                'iva' => $iva,
                'numero_clave_prod' => (string)($c['clave_prod_serv'] ?? ''),
                'numero_clave_unidad' => (string)($c['clave_unidad'] ?? ''),
            ]);
        }

        // 3) IMPUESTOS (facturas_impuestos)
        $ivaTotal = (float) DB::table('factura_detalles')
            ->where('users_facturas_id', $facturaId)
            ->sum('iva');

        if ($ivaTotal > 0) {
            DB::table('facturas_impuestos')->insert([
                'users_facturas_id' => $facturaId,
                'impuesto' => '002',
                'tipo' => 'TRAS',  // ✅ convención FactuCare
                'tasa' => 16,
                'monto' => $ivaTotal,
            ]);
        }

        return (int)$facturaId;
    }


    /**
     * Incrementa folio y consume 1 timbre.
     * Se ejecuta SOLO si el timbrado fue exitoso.
     */
    private function avanzarFolioYConsumirTimbre(int $userId, int $folioId): void
    {
        if ($folioId <= 0) {
            throw new \RuntimeException('No viene folio_id en el payload, no puedo avanzar folio/consumir timbre.');
        }

        // 1) Lock del folio
        $folio = \DB::table('folios')
            ->where('id', $folioId)
            ->where('users_id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$folio) {
            throw new \RuntimeException("No existe folio {$folioId} para este usuario.");
        }

        // 2) Subir el folio (en tu BD es la columna `folio`)
        \DB::table('folios')
            ->where('id', $folioId)
            ->where('users_id', $userId)
            ->update([
                'folio' => ((int)$folio->folio) + 1,
            ]);

        // 3) Consumir 1 timbre del usuario (users.timbres_disponibles)
        $u = \DB::table('users')
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$u) {
            throw new \RuntimeException("No existe el usuario {$userId}.");
        }

        if (!isset($u->timbres_disponibles)) {
            throw new \RuntimeException('La columna users.timbres_disponibles no existe o no está disponible.');
        }

        $actual = (int)$u->timbres_disponibles;

        if ($actual <= 0) {
            throw new \RuntimeException('No tienes timbres disponibles para timbrar.');
        }

        \DB::table('users')
            ->where('id', $userId)
            ->update([
                'timbres_disponibles' => $actual - 1,
            ]);

        // (Opcional) aquí podrías registrar un movimiento en timbres_movs si lo quieres auditable
    }






    /* ==========================
       Regenerar PDF (opcional)
    ========================== */

    public function regenerarPdf(int $id)
    {
        $userId = auth()->id();
        $factura = $this->facturaOrFail($id);

        $xml = (string) ($factura->xml ?? '');
        if (trim($xml) === '') {
            return back()->with('error', 'No hay XML para regenerar el PDF.');
        }

        // Intentamos PAC primero (como FC1), y si falla, fallback Dompdf.
        $pdfB64 = '';

        try {
            // Para regenerar, armamos un “payload mínimo” desde la factura
            $payloadMin = [
                'tipo_comprobante' => $factura->tipo_comprobante ?? 'I',
                'comentarios_pdf'  => $factura->comentarios_pdf ?? '',
                'serie'            => $factura->serie ?? null,
                'folio'            => $factura->folio ?? null,
            ];

            // Cliente mínimo (para json)
            $clienteMin = (object)[
                'rfc'          => $factura->rfc ?? '',
                'razon_social' => $factura->razon_social ?? '',
            ];

            $pdfB64 = $this->generarPdfBase64DesdePacV33($userId, $xml, $payloadMin, $clienteMin);
        } catch (\Throwable $e) {
            $pdfB64 = $this->generarPdfBase64FallbackDompdf($xml);
        }

        if (!is_string($pdfB64)) $pdfB64 = '';
        if (trim($pdfB64) === '') {
            return back()->with('error', 'No fue posible regenerar el PDF (PAC y fallback fallaron).');
        }

        DB::table('facturas')
            ->where('id', $factura->id)
            ->where('users_id', $userId)
            ->update(['pdf' => $pdfB64]);

        return back()->with('success', 'PDF regenerado correctamente.');
    }


    public function cancelar(Request $request, int $id)

    {
        $userId = auth()->id();

        $motivo = (string) $request->input('motivo', '');
        $folioSustitucion = (string) $request->input('folioSustitucion', '');

        if (!in_array($motivo, ['01','02','03','04'], true)) {
            return back()->with('error', 'Motivo inválido.');
        }
        if ($motivo === '04' && trim($folioSustitucion) === '') {
            return back()->with('error', 'Para motivo 04 es obligatorio el UUID de sustitución.');
        }

        $factura = DB::table('facturas')
            ->where('id', $id)
            ->where('users_id', $userId)
            ->first();

        if (!$factura) {
            return back()->with('error', 'Factura no encontrada.');
        }

        if (strtoupper((string)$factura->estatus) === 'CANCELADA') {
            return back()->with('error', 'La factura ya está cancelada.');
        }

        $xml = (string)($factura->xml ?? '');
        if (trim($xml) === '') {
            return back()->with('error', 'No hay XML timbrado para cancelar.');
        }

        // De XML sacamos: uuid, rfc emisor/receptor y total exacto
        $meta = $this->parseCfdiBasics($xml);
        $parties = $this->parseCfdiParties($xml);

        $uuid = (string)($meta['uuid'] ?? $factura->uuid ?? '');
        $rfcEmisor = (string)($parties['emisor_rfc'] ?? '');
        $rfcReceptor = (string)($parties['receptor_rfc'] ?? '');
        $totalRaw = (string)($meta['total'] ?? '0');
        $totalRaw = str_replace([',', ' '], '', $totalRaw);   // por si viniera con separadores
        $totalNum = round((float)$totalRaw, 2);

        if ($totalNum <= 0) {
            return back()->with('error', 'No pude obtener el Total desde el XML.');
        }

        // MUY IMPORTANTE: mandar con 2 decimales exactos
        $totalPac = number_format($totalNum, 2, '.', '');


        if ($uuid === '' || $rfcEmisor === '' || $rfcReceptor === '' || $totalPac <= 0) {
            return back()->with('error', 'No pude obtener UUID/RFCs/Total desde el XML.');
        }

        try {
            // CSD actual (ya lo tienes hecho para timbrar)
            $csd = $this->cargarCsdParaTimbrado($userId);

            $keyPem = (string)($csd['key_pem'] ?? '');
            $certB64 = (string)($csd['cert_b64'] ?? '');

            if (trim($keyPem) === '' || trim($certB64) === '') {
                throw new \RuntimeException('CSD incompleto: falta key_pem o cert_b64.');
            }

            // certB64 (DER) -> PEM
            $cerPem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($certB64, 64, "\n")
                . "-----END CERTIFICATE-----\n";

            $mp = new MultiPac();

            $resp = $mp->callCancelarPEM([
                'keyPEM' => $keyPem,
                'cerPEM' => $cerPem,
                'uuid' => $uuid,
                'rfcEmisor' => $rfcEmisor,
                'rfcReceptor' => $rfcReceptor,
                'total' =>  $totalPac,
                'motivo' => $motivo,
                'folioSustitucion' => $motivo === '04' ? $folioSustitucion : '',
            ]);

            // Normalizar respuesta
            if (is_string($resp)) {
                // SOAP raw response (probable error)
                throw new \RuntimeException('PAC (respuesta): ' . mb_substr($resp, 0, 600));
            }

            $status  = strtolower((string)($resp->status ?? $resp->STATUS ?? ''));
            $code    = (string)($resp->code ?? $resp->codigo ?? $resp->CODIGO ?? '');
            $message = (string)($resp->message ?? $resp->mensaje ?? $resp->MENSAJE ?? '');
            $acuse   = (string)($resp->data ?? $resp->acuse ?? $resp->ACUSE ?? '');

            $ok = ($status === 'success') || ($code === '0' || $code === 0);

            if (!$ok) {
                $msgHumano = $this->traducirCodigoPac('cancelar', (string)$code, $message);
                throw new \RuntimeException($msgHumano ?: ($message ?: 'Cancelación rechazada por el PAC.'));
            }

            // Guardar en DB
            DB::table('facturas')
                ->where('id', $factura->id)
                ->update([
                    'estatus' => 'CANCELADA',
                    'acuse' => $acuse !== '' ? $acuse : (string)($factura->acuse ?? ''),
                ]);

            return back()->with('success', 'Factura cancelada correctamente.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Error al cancelar: ' . $e->getMessage());
        }
    }


    private function traducirCodigoPac(string $operacion, string $code, ?string $mensajeApi = null): string
    {
        $dic = config('timbradorxpress_errors', []);

        $msg = null;

        // orden de prioridad: operacion -> general -> mensaje que trajo la API
        if (isset($dic[$operacion]) && is_array($dic[$operacion]) && isset($dic[$operacion][$code])) {
            $msg = $dic[$operacion][$code];
        } elseif (isset($dic['general']) && is_array($dic['general']) && isset($dic['general'][$code])) {
            $msg = $dic['general'][$code];
        } elseif (!empty($mensajeApi)) {
            $msg = $mensajeApi;
        } else {
            $msg = "Código desconocido: {$code}";
        }

        return $msg;
    }

    private function generarPdfBase64DesdePacV33(int $userId, string $xmlTimbrado, array $payload, object $cliente): string
    {
        // En FC1 se manda xmlB64 + plantilla + json + logo
        $xmlB64 = base64_encode($xmlTimbrado);

        // Plantilla: si todavía no manejas plantillas en FC2, dejamos 1.
        // (Luego lo conectamos a tu tabla/setting real)
        $plantilla = 1;

        // Logo: si todavía no lo manejas en FC2, manda string vacío.
        $logoB64 = '';

        // JSON: lo usamos para datos de impresión (FC1 lo manda base64)
        $tipo = (string)($payload['tipo_comprobante'] ?? 'I');
        $tipoNombre = ($tipo === 'I') ? 'INGRESO' : (($tipo === 'E') ? 'EGRESO' : (($tipo === 'T') ? 'TRASLADO' : $tipo));

        $jsonArr = [
            'tipo_comprobante' => $tipo,
            'tipo_nombre' => $tipoNombre,
            'receptor_rfc' => (string)($cliente->rfc ?? ''),
            'receptor_razon_social' => (string)($cliente->razon_social ?? ''),
            'comentarios_pdf' => (string)($payload['comentarios_pdf'] ?? ''),
            'serie' => (string)($payload['serie'] ?? ''),
            'folio' => (string)($payload['folio'] ?? ''),
        ];

        $jsonB64 = base64_encode(json_encode($jsonArr, JSON_UNESCAPED_UNICODE));

        $mp = new MultiPac();

        $resp = $mp->generatePDFV33([
            'xmlB64' => $xmlB64,
            'plantilla' => $plantilla,
            'json' => $jsonB64,
            'logo' => $logoB64,
        ]);

        // Si regresó string (SOAP raw), lo tratamos como error
        if (is_string($resp)) {
            throw new \RuntimeException('PAC PDF (SOAP): ' . mb_substr(strip_tags($resp), 0, 500));
        }

        // Normalizamos: en FC1 el éxito es code "210" y viene ->pdf
        $code = (string)($resp->code ?? $resp->codigo ?? $resp->CODIGO ?? '');
        $msg  = (string)($resp->message ?? $resp->mensaje ?? $resp->MENSAJE ?? '');

        $pdf = (string)($resp->pdf ?? $resp->PDF ?? '');

        if ($code !== '' && $code !== '210' && $pdf === '') {
            // Si tienes diccionario: traducirCodigoPac('generarPDF', $code, $msg)
            $friendly = method_exists($this, 'traducirCodigoPac')
                ? $this->traducirCodigoPac('generarPDF', $code, $msg)
                : ($msg ?: "Código PAC: {$code}");

            throw new \RuntimeException($friendly);
        }

        if (trim($pdf) === '') {
            throw new \RuntimeException('PAC no devolvió PDF (base64) en generarPDFV33.');
        }

        return $pdf;
    }

    private function generarPdfBase64FallbackDompdf(string $xmlTimbrado): string
    {
        // Fallback para que NO se pierda el PDF si el PAC no lo entrega
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return '';
        }

        $meta = $this->parseCfdiBasics($xmlTimbrado);

        $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadView('facturas.pdf', [
            'factura' => null,
            'meta' => $meta,
            'xml' => $xmlTimbrado,
        ])->output();

        return base64_encode($pdfBinary);
    }


}
