<?php

namespace App\Extensions\MultiPac;

use Carbon\Carbon;

use SoapClient;

use App;
use Log;
use SimpleXMLElement;
use LSS\Array2XML;

class MultiPac {

    private $client;
    private $clientNomina;
    private $clientTools;
    private $clientToolsV33;

    private $usuario;
    private $usuarioNomina;
    private $usuarioTools;

    private $password;
    private $passwordNomina;
    private $passwordTools;

    private $apiKey;

    public function __construct()
        {
            $mode = env('MULTIPAC_MODE', app()->environment('production') ? 'prod' : 'dev');

            $wsdl = $mode === 'prod'
                ? env('MULTIPAC_WSDL_PROD', 'https://app.timbradorxpress.mx/ws/servicio.do?wsdl')
                : env('MULTIPAC_WSDL_DEV', 'https://dev.timbradorxpress.mx/ws/servicio.do?wsdl');

            $this->apiKey = $mode === 'prod'
                ? env('MULTIPAC_APIKEY_PROD')
                : env('MULTIPAC_APIKEY_DEV');

            if (empty($this->apiKey)) {
                throw new \RuntimeException("MULTIPAC_APIKEY no configurada para mode={$mode}");
            }

            // SOAP timbrado (TimbradorXpress)
            $this->client = new \SoapClient($wsdl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 30,
            ]);

            // Si sigues usando el servicio de PDFs externo (WSTools33), déjalo tal cual lo traías:
            $this->clientToolsV33 = new \SoapClient("http://facturaloplus.com/ws/WSTools33.php?wsdl", [ 'trace' => true ]);

            // OJO: estos 4 también conviene pasarlos a .env después (no es requisito para timbrar)
            $this->usuarioTools = $this->usuarioTools ?? 'CCA1510307X6';
            $this->passwordTools = $this->passwordTools ?? 'cKy1uaCvTKCB';

            // Ya no necesitas usuario/password para timbrar, porque usas apikey (callTimbrarCFDI)
        }


    public function callMethod($method, $data = []) {
        $params = [
            'usuario' => $this->usuario,
            'password' => $this->password,
        ];

        $params += $data;
        return $this->client->__soapCall($method, $params);
    }

    public function callMethodWithNameClaveAcceso($method, $data = []) {
        ini_set('default_socket_timeout', 600000);
        $params = [
            'usuario' => $this->usuario,
            'claveAcceso' => $this->password,
        ];
        $params += $data;
        
        try{
            return $this->clientNomina->__soapCall($method, $params);
        } catch(\SoapFault $ex){
            dump($ex);
            die;
        }
    }

    public function callConsultarAutorizacionesPendientes($data) {
        $params = [
            'usuario' => $this->usuario,
            'claveAcceso' => $this->password,
        ];
        $params += $data;
        try{
            return $this->clientNomina->ConsultarAutorizacionesPendientes($params['usuario'], $params['claveAcceso'], $params['PrivateKeyPem'], $params['PrivateKeyPem']);
        } catch(\SoapFault $ex){
            return $this->clientNomina->__getLastResponse();
        }
    }
    
     public function callMethod2($method, $data = []) {
        $params = [
            //'usuario' => $this->usuario,
            'apikey' => $this->apiKey
        ];
        
        $params += $data;
        //dump($params, $this->client->__soapCall($method, $params));die;
        return $this->client->__soapCall($method, $params);
    }

    public function callTimbrarCFDI($data) {
        $params = [
            'apikey' => $this->apiKey
        ];
        $params += $data;

        try {
            return $this->client->timbrarConSello($params['apikey'], $params['xmlCFDI'], $params['keyPEM']);
        } catch (\SoapFault $ex) {
            return $this->client->__getLastResponse();
        }
    }

    public function generatePDF($data) {
        $params = [
            'usuario' => $this->usuarioTools,
            'claveAcceso' => $this->passwordTools,
        ];
        $params += $data;

        try {
            return $this->clientTools->generarPDF($params['usuario'], $params['claveAcceso'], $params['xmlB64'], $params['plantilla']);
        } catch (\SoapFault $ex) {
            return $this->clientTools->__getLastResponse();
        }
    }

    public function generatePDFV33($data) {
        $params = [
            'usuario' => $this->usuarioTools,
            'claveAcceso' => $this->passwordTools,
        ];
        $params += $data;

        try {
           return $this->clientToolsV33->generarPDF($params['usuario'], $params['claveAcceso'], $params['xmlB64'], $params['plantilla'], $params['json'], $params['logo']);
        } catch (\SoapFault $ex) {
            return $this->clientTools->__getLastResponse();
        }
    }

    public function generateSello($data) {
        $params = [
            'usuario' => $this->usuarioTools,
            'claveAcceso' => $this->passwordTools,
        ];
        $params += $data;

        try {
            return $this->clientTools->generarSello($params['usuario'], $params['claveAcceso'], $params['xmlB64'], $params['keyPEMB64']);
        } catch (\SoapFault $ex) {
            return $this->clientTools->__getLastResponse();
        }
    }

    public function generateSelloV33($data) {
        $params = [
            'usuario' => $this->usuarioTools,
            'claveAcceso' => $this->passwordTools,
        ];
        $params += $data;

        try {
            return $this->clientToolsV33->generarSello($params['usuario'], $params['claveAcceso'], $params['xmlB64'], $params['keyPEMB64']);
        } catch (\SoapFault $ex) {
            return $this->clientTools->__getLastResponse();
        }
    }

    public function callCancelarPEM(array $data)
    {
        $params = [
            'apikey' => $this->apiKey,
        ] + $data;

        try {
            // cancelarPEM(apikey, keyPEM, cerPEM, uuid, rfcEmisor, rfcReceptor, total, motivo, folioSustitucion)
            return $this->client->cancelarPEM(
                $params['apikey'],
                $params['keyPEM'],
                $params['cerPEM'],
                $params['uuid'],
                $params['rfcEmisor'],
                $params['rfcReceptor'],
                $params['total'],
                $params['motivo'],
                $params['folioSustitucion'] ?? ''
            );
        } catch (\SoapFault $ex) {
            return $this->client->__getLastResponse();
        }
    }


    public function generarFacturaWhitData($user, $userFactura, $data) {

    // -----------------------------
    // Helpers locales (solo para esta función)
    // -----------------------------
    $resolveDocPath = function($doc): string {
        $pathDb = '';
        $name   = '';
    
        if (is_object($doc)) {
            if (method_exists($doc, 'getPath')) {
                $pathDb = (string)$doc->getPath();
            } elseif (property_exists($doc, '_path')) {
                $pathDb = (string)$doc->_path;
            }
    
            if (method_exists($doc, 'getName')) {
                $name = (string)$doc->getName();
            } elseif (property_exists($doc, '_name')) {
                $name = (string)$doc->_name;
            }
        }
    
        $pathDb = trim($pathDb);
    
        // Normaliza: si es relativo -> public_path()
        $normalize = function(string $p): string {
            $p = trim($p);
            if ($p === '') return $p;
    
            // absoluto Linux (/...) o Windows (C:\...)
            if ($p[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $p)) {
                return $p;
            }
    
            return public_path(ltrim($p, "/\\"));
        };
    
        // 1) Si _path existe como archivo, úsalo
        if ($pathDb !== '') {
            $p = $normalize($pathDb);
    
            if (is_file($p)) {
                return $p;
            }
    
            // 2) Si _path era carpeta/base, intenta _path/_name (sin duplicar)
            if ($name !== '') {
                if (basename($p) !== $name) {
                    $cand = rtrim($p, "/\\") . DIRECTORY_SEPARATOR . $name;
                    if (is_file($cand)) return $cand;
                }
            }
    
            // si no existió, regresa el path normalizado para que el error sea claro
            return $p;
        }
    
        // 3) Sin _path: fallback por nombre, pero ABSOLUTO
        if ($name === '') {
            return '';
        }
    
        $fallback = public_path('uploads/users_documentos' . DIRECTORY_SEPARATOR . $name);
        return $fallback;
    };


    $resolveKeyPemPath = function(string $keyPath): string {
    $keyPath = trim($keyPath);
    if ($keyPath === '') return $keyPath;

    // si ya es pem
    if (preg_match('/\.pem$/i', $keyPath)) {
        return $keyPath;
    }

    // tu caso real en server: archivo.key.pem
    $cand1 = $keyPath . '.pem';

    // fallback: archivo.key -> archivo.pem
    $cand2 = preg_replace('/\.key$/i', '.pem', $keyPath);

    if (is_file($cand1)) return $cand1;
    if (is_file($cand2)) return $cand2;

    return $cand1; // para mostrar qué esperaba
};

    // -----------------------------
    // Helpers para formato CFDI 3.3
    // -----------------------------
    $fmt2 = function($n): string {
        return number_format((float)$n, 2, '.', '');
    };

    $fmt6 = function($n): string {
        return number_format((float)$n, 6, '.', '');
    };

    // Acumulador de traslados globales: key = Impuesto|TipoFactor|TasaOCuota
    $trasladosGlobal = []; // [key => ['Impuesto'=>..., 'TipoFactor'=>..., 'TasaOCuota'=>..., 'Importe'=>float]]
    $addTrasladoGlobal = function(string $impuesto, string $tipoFactor, string $tasaOCuota, float $importe) use (&$trasladosGlobal) {
        $key = $impuesto.'|'.$tipoFactor.'|'.$tasaOCuota;
        if (!isset($trasladosGlobal[$key])) {
            $trasladosGlobal[$key] = [
                'Impuesto'   => $impuesto,
                'TipoFactor' => $tipoFactor,
                'TasaOCuota' => $tasaOCuota,
                'Importe'    => 0.0,
            ];
        }
        // suma a 2 decimales por seguridad
        $trasladosGlobal[$key]['Importe'] = round($trasladosGlobal[$key]['Importe'] + $importe, 2);
    };


    // -----------------------------
    // Tu código original (con paths corregidos)
    // -----------------------------

    $cerFile = $user->getInfoFactura()->getDocumentByType(App\Models\UsersInfoFacturaDocumentos::CERTIFICADO);

    $fechaFactura = new Carbon();
    $diasDiferencia = $fechaFactura->diffInDays(Carbon::now());
    if ($diasDiferencia > 3) {
        Flash::error('Factura fuera de fecha');
        return redirect()->action('Users\FacturasV33Controller@getIndex');
    }

    $tipoDocumento = strtolower(App\Models\Facturas::getTipoDocumento('FACTURA'));
    $f = $user->getFolioByTipo(App\Models\Facturas::getTipoDocumento('FACTURA'));
    if (empty($f)) {
        Flash::error('No existe un folio configurado para ese tipo de Documento');
        return redirect()->action('Users\FacturasV33Controller@getIndex');
    }

    $serie = $f->getSerie();
    $folio = $f->getFolio();
    $fechaFactura = $fechaFactura->format('Y-m-d\TH:i:s');
    $noCertificado = $cerFile->getNumeroCertificado();
    $moneda = 'MXN';
    $tipoComprobante = 'I';
    $formaPago = '04';
    $metodoPago = 'PUE';
    $tipoCambio = '1.0';
    $usoCFDI = 'G03';
    $perfil = $user->getPerfil();
    $lugarExpedicion = $perfil->getCodigoPostal();

    $dataXml = [
        '@attributes' => [
            'xsi:schemaLocation' => 'http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd',
            'xmlns:cfdi' => 'http://www.sat.gob.mx/cfd/3',
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'Version' => '3.3',
            'Serie' => $serie,
            'Folio' => $folio,
            'Fecha' => $fechaFactura,
            'Sello' => '',
            'NoCertificado' => $noCertificado,
            'Certificado' => '',
            'SubTotal' => '',
            'Moneda' => $moneda,
            'Total' => '',
            'TipoDeComprobante' => $tipoComprobante,
            'FormaPago' => $formaPago,
            'MetodoPago' => $metodoPago,
            'TipoCambio' => $tipoCambio,
            'LugarExpedicion' => $lugarExpedicion,
        ],
        'cfdi:Emisor' => [
            '@attributes' => [
                'Rfc' => $perfil->getRfc(),
                'Nombre' => $perfil->getRazonSocial(),
                'RegimenFiscal' => $perfil->getNumeroRegimen(),
            ],
        ],
        'cfdi:Receptor' => [
            '@attributes' => [
                'Rfc' => $userFactura->getPerfil()->getRfc(),
                'Nombre' => $userFactura->getPerfil()->getRazonSocial(),
                'UsoCFDI' => $usoCFDI,
            ],
        ],
        'cfdi:Conceptos' => [],
        'cfdi:Impuestos' => [
            '@attributes' => [
                'TotalImpuestosRetenidos' => '',
                'TotalImpuestosTrasladados' => '',
            ],
        ],
    ];

    $xml = Array2XML::createXML('cfdi:Comprobante', $dataXml);
    $conceptos = $xml->getElementsByTagName('cfdi:Conceptos')[0];

    foreach ($data['conceptos'] as $conceptoData) {
        $concepto = $xml->createElement('cfdi:Concepto');

        $impuestos = $xml->createElement('cfdi:Impuestos');
        $retenciones = $xml->createElement('cfdi:Retenciones');
        $traslados = $xml->createElement('cfdi:Traslados');

        $concepto->setAttribute('ClaveProdServ', $conceptoData['ClaveProdServ']);
        $concepto->setAttribute('ClaveUnidad', $conceptoData['ClaveUnidad']);
        $concepto->setAttribute('NoIdentificacion', $conceptoData['NoIdentificacion']);
        $concepto->setAttribute('Cantidad', $conceptoData['Cantidad']);
        $concepto->setAttribute('Unidad', $conceptoData['Unidad']);
        $concepto->setAttribute('Descripcion', $conceptoData['Descripcion']);
        $concepto->setAttribute('ValorUnitario', $conceptoData['ValorUnitario']);
        $concepto->setAttribute('Importe', $conceptoData['Importe']);
        $concepto->setAttribute('Descuento', $conceptoData['Descuento']);

        foreach ($conceptoData['ImpuestosTrasladados'] as $trasladoData) {
            $impuesto    = (string)($trasladoData['Impuesto'] ?? '002');     // IVA=002
            $tipoFactor  = (string)($trasladoData['TipoFactor'] ?? 'Tasa');  // Tasa
            $tasaOCuota  = $fmt6($trasladoData['TasaOCuota'] ?? 0.16);

            // Base a 2 decimales (Importe - Descuento por concepto normalmente)
            $base = (float)($trasladoData['Base'] ?? 0);
            $base2 = $fmt2($base);

            // Importe SIEMPRE como round(base * tasa, 2) para que global = suma por concepto
            $importeCalc = round(((float)$base) * ((float)$tasaOCuota), 2);
            $importe2 = $fmt2($importeCalc);

            // Nodo traslado por concepto
            $traslado = $xml->createElement('cfdi:Traslado');
            $traslado->setAttribute('Base', $base2);
            $traslado->setAttribute('Impuesto', $impuesto);
            $traslado->setAttribute('TipoFactor', $tipoFactor);
            $traslado->setAttribute('TasaOCuota', $tasaOCuota);
            $traslado->setAttribute('Importe', $importe2);
            $traslados->appendChild($traslado);

            // Acumular al global (la clave del CFDI40221)
            $addTrasladoGlobal($impuesto, $tipoFactor, $tasaOCuota, $importeCalc);
        }

        if ($traslados->childNodes->length > 0) {
            $impuestos->appendchild($traslados);
        }
        if ($impuestos->childNodes->length > 0) {
            $concepto->appendChild($impuestos);
        }

        $conceptos->appendchild($concepto);
    }

    $comprobante = $xml->getElementsByTagName('cfdi:Comprobante')[0];
    $comprobante->setAttribute('Total', $data['Total']);
    $comprobante->setAttribute('SubTotal', $data['SubTotal']);
    $comprobante->setAttribute('Descuento', $data['Descuento']);

    // Obtener el cfdi:Impuestos GLOBAL (hijo directo del Comprobante)
    $impuestosSuma = null;
    foreach ($comprobante->childNodes as $n) {
        if ($n->nodeType === XML_ELEMENT_NODE && $n->nodeName === 'cfdi:Impuestos') {
            $impuestosSuma = $n;
            break;
        }
    }
    if (!$impuestosSuma) {
        $impuestosSuma = $xml->createElement('cfdi:Impuestos');
        $comprobante->appendChild($impuestosSuma);
    }

    // Construir Traslados globales desde lo sumado por concepto
    $trasladosSuma = $xml->createElement('cfdi:Traslados');

    $totalTrasladados = 0.0;
    foreach ($trasladosGlobal as $row) {
        $totalTrasladados = round($totalTrasladados + (float)$row['Importe'], 2);

        $trasladoSuma = $xml->createElement('cfdi:Traslado');
        $trasladoSuma->setAttribute('Impuesto', $row['Impuesto']);
        $trasladoSuma->setAttribute('TipoFactor', $row['TipoFactor']);
        $trasladoSuma->setAttribute('TasaOCuota', $row['TasaOCuota']);
        $trasladoSuma->setAttribute('Importe', $fmt2($row['Importe']));
        $trasladosSuma->appendChild($trasladoSuma);
    }

    if ($trasladosSuma->childNodes->length > 0) {
        $impuestosSuma->appendChild($trasladosSuma);
        $impuestosSuma->setAttribute('TotalImpuestosTrasladados', $fmt2($totalTrasladados));
    }


    // -----------------------------
    // ✅ AQUI: PATHS CORREGIDOS (CER/KEY)
    // -----------------------------

    $keyFile = $user->getInfoFactura()->getDocumentByType(\App\Models\UsersInfoFacturaDocumentos::LLAVE);

    // Resolver rutas reales
    $cerPath = $resolveDocPath($cerFile);
    $keyPath = $resolveDocPath($keyFile);
    $keyPemPath = $resolveKeyPemPath($keyPath);

    if (!is_file($cerPath)) {
        Flash::error("No se encontró el CERTIFICADO (.cer) en: {$cerPath}");
        return redirect()->action('Users\FacturasV33Controller@getIndex');
    }
    if (!is_file($keyPemPath)) {
        Flash::error("No se encontró la LLAVE (.pem) en: {$keyPemPath}");
        return redirect()->action('Users\FacturasV33Controller@getIndex');
    }

    // Generar sello
    $params = [
        'xmlB64'     => base64_encode($xml->saveXml()),
        'keyPEMB64'  => base64_encode(file_get_contents($keyPemPath)),
    ];

    $response = self::generateSelloV33($params);

    $domDoc = new \DomDocument();
    $domDoc->loadXML($xml->saveXml()) or die("XML invalido");
    $c = $domDoc->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);

    $c->setAttribute('Sello', $response->sello);

    // Certificado desde la ruta real
    $certificado = str_replace(["\n", "\r"], '', base64_encode(file_get_contents($cerPath)));
    $c->setAttribute('Certificado', $certificado);

    $doc = $domDoc->savexml();

    $params = [
        'cfdiB64' => base64_encode($doc),
    ];
    file_put_contents(storage_path('logs/xml_debug_cfdi.xml'), $doc);
    $response = self::callTimbrarCFDI($params);
    if (is_string($response)) {
        return false;
    }

    switch ($response->codRetorno) {
        case 200:
            $logo = 'null';
            if (!empty($user->getLogo()) && file_exists('uploads/users_logos/thumbnails/' . $user->getLogo()->getName())) {
                $logo = base64_encode(file_get_contents('uploads/users_logos/thumbnails/' . $user->getLogo()->getName()));
            }
            $params = [
                'xmlB64' => base64_encode($response->cfdiTimbrado),
                'plantilla' => 1,
                'json' => 'null',
                'logo' => base64_encode(file_get_contents('uploads/users_logos/thumbnails/' . $user->getLogo()->getName())),
            ];

            $attachments = [];
            $pdfResponse = self::generatePDFV33($params);
            if ($pdfResponse->code === "210") {
                $pdf = $pdfResponse->pdf;
                $attachments["{$response->uuid}.pdf"] = base64_decode($pdf);
            }

            $attachments = [ "{$response->uuid}.xml" => $response->cfdiTimbrado ];
            $dataEmail = [];
            $email = $data['email'];
            $title = 'Factura generada';
            \Mail::send('emails.facturacion.factura_generada', $dataEmail, function($message) use ($email, $title, $attachments) {
                $message->from('info@factucare.com', 'Factucare');
                $message->subject($title);
                $message->to($email, $email);
                foreach ($attachments as $nameFile => $attach) {
                    $message->attachData($attach, $nameFile);
                }
            });
            return [ 'xml' => $response->cfdiTimbrado ];
            break;
        default:
            return false;
    }
}

}

?>