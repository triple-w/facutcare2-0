<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;

trait PacMultipacTrait
{
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

        $normalizePath = function (string $p): string {
            $p = trim($p);
            if ($p === '') return '';
            if ($p[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $p)) return $p;
            return public_path(ltrim($p, "/\\"));
        };

        $resolveDocFile = function (object $doc) use ($normalizePath): string {
            $pathDb = trim((string)($doc->_path ?? ''));
            $name   = trim((string)($doc->_name ?? ''));

            if ($pathDb !== '') {
                $p = $normalizePath($pathDb);
                if (is_file($p)) return $p;

                if ($name !== '') {
                    if (basename($p) !== $name) {
                        $cand = rtrim($p, "/\\") . DIRECTORY_SEPARATOR . $name;
                        if (is_file($cand)) return $cand;
                    }
                }
                return $p;
            }

            if ($name === '') return '';
            return public_path('uploads/users_documentos' . DIRECTORY_SEPARATOR . $name);
        };

        $resolveKeyPemPath = function (string $keyPath): string {
            $keyPath = trim($keyPath);
            if ($keyPath === '') return $keyPath;

            if (preg_match('/\.pem$/i', $keyPath)) return $keyPath;

            $cand1 = $keyPath . '.pem';                // archivo.key.pem (tu caso real)
            $cand2 = preg_replace('/\.key$/i', '.pem', $keyPath); // archivo.pem

            if (is_file($cand1)) return $cand1;
            if (is_file($cand2)) return $cand2;

            return $cand1;
        };

        $cer = $docs->first(function ($d) {
            return ($d->tipo === 'ARCHIVO_CERTIFICADO') && str_ends_with(strtolower((string)$d->_name), '.cer');
        });

        if (!$cer) {
            throw new \RuntimeException('No se encontró ARCHIVO_CERTIFICADO .cer.');
        }

        $keyDoc = $docs->first(function ($d) {
            return ($d->tipo === 'ARCHIVO_LLAVE');
        });

        if (!$keyDoc) {
            throw new \RuntimeException('No se encontró ARCHIVO_LLAVE.');
        }

        $cerPath = $resolveDocFile($cer);
        $keyPath = $resolveDocFile($keyDoc);
        $keyPemPath = $resolveKeyPemPath($keyPath);

        if (!is_file($cerPath)) {
            throw new \RuntimeException("No existe el archivo .cer en: {$cerPath}");
        }
        if (!is_file($keyPemPath)) {
            throw new \RuntimeException("No existe el archivo .pem en: {$keyPemPath} (derivado de: {$keyPath})");
        }

        $certB64 = base64_encode(file_get_contents($cerPath));
        $keyPem  = file_get_contents($keyPemPath);

        $noCert = trim((string)($cer->numero_certificado ?? $cer->no_certificado ?? $cer->numero ?? ''));
        if ($noCert === '') {
            throw new \RuntimeException('El documento .cer no tiene numero_certificado en BD.');
        }

        return [
            'cert_b64'       => $certB64,
            'no_certificado' => $noCert,
            'key_pem'        => $keyPem,
            'cer_path'       => $cerPath,
            'key_pem_path'   => $keyPemPath,
        ];
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
        $comprobante->setAttribute('Sello', '');

        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    private function extraerUuidDeXml(string $xml): string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml)) {
            $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
            if (!$dom->loadXML($xml2)) return '';
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        /** @var \DOMElement|null $t */
        $t = $xp->query('//tfd:TimbreFiscalDigital')->item(0);
        return ($t instanceof \DOMElement) ? (string)$t->getAttribute('UUID') : '';
    }

    private function timbrarConPacMultipac(int $userId, string $xmlOriginal): array
    {
        $csd = $this->cargarCsdParaTimbrado($userId);

        $certB64 = (string)($csd['cert_b64'] ?? '');
        $noCert  = (string)($csd['no_certificado'] ?? '');
        $keyPem  = (string)($csd['key_pem'] ?? '');

        if ($certB64 === '' || $noCert === '' || $keyPem === '') {
            throw new \RuntimeException('CSD incompleto: falta cert_b64 / no_certificado / key_pem.');
        }

        $xmlParaPac = $this->inyectarCertificadoEnXml($xmlOriginal, $certB64, $noCert);

        // Ajusta namespace si tu clase está en otro lugar
        $mp = new \App\Extensions\MultiPac\MultiPac();

        $r = $mp->callTimbrarCFDI([
            'xmlCFDI' => $xmlParaPac,
            'keyPEM'  => $keyPem,
        ]);

        if (is_string($r)) {
            throw new \RuntimeException('PAC (respuesta): ' . mb_substr($r, 0, 800));
        }

        $code    = (string)($r->code ?? $r->CODIGO ?? $r->codigo ?? '');
        $mensaje = (string)($r->message ?? $r->MENSAJE ?? $r->mensaje ?? '');

        $xmlTimbrado = (string)($r->data ?? $r->xml ?? $r->XML ?? $r->cfdi ?? $r->CFDI ?? '');
        if (trim($xmlTimbrado) === '') {
            $txt = $mensaje !== '' ? $mensaje : 'Sin mensaje del PAC.';
            if ($code !== '') $txt = "PAC error ({$code}): " . $txt;
            throw new \RuntimeException($txt);
        }

        $uuid = (string)($r->uuid ?? $r->UUID ?? '');
        if ($uuid === '') $uuid = $this->extraerUuidDeXml($xmlTimbrado);

        $pdfB64 = (string)($r->pdf ?? $r->PDF ?? $r->pdfB64 ?? $r->PDFB64 ?? '');
        $pdfB64 = trim($pdfB64) !== '' ? $pdfB64 : null;

        $acuse = (string)($r->acuse ?? $r->ACUSE ?? '');

        return [
            'xml'     => $xmlTimbrado,
            'uuid'    => $uuid,
            'pdf'     => $pdfB64,
            'acuse'   => $acuse !== '' ? $acuse : null,
            'mensaje' => $mensaje !== '' ? $mensaje : null,
            'code'    => $code !== '' ? $code : null,
        ];
    }

    private function generarPdfBase64DesdePacV33(int $userId, string $xmlTimbrado, array $payload, object $cliente): string
    {
        $xmlB64 = base64_encode($xmlTimbrado);

        // Detecta tipo de comprobante (si no viene, inferimos Pagos si trae forma_pago_p/pagos)
        $tipo = (string)($payload['tipo_comprobante'] ?? '');
        if ($tipo === '') {
            $tipo = (!empty($payload['pagos']) || !empty($payload['forma_pago_p'])) ? 'P' : 'I';
        }

        // Plantilla:
        // - Para pagos, típicamente Multipac usa "pagos2"
        // - Para facturas, si tú no tienes mapeo aún, dejamos 1
        $plantilla = $payload['plantilla_pdf']
            ?? (($tipo === 'P') ? 'pagos2' : 1);

        // Logo: si aún no lo manejas aquí, queda vacío
        $logoB64 = '';

        // Tipo nombre (para PDF)
        $tipoNombre = match ($tipo) {
            'I' => 'INGRESO',
            'E' => 'EGRESO',
            'T' => 'TRASLADO',
            'P' => 'PAGO',
            default => $tipo,
        };

        // JSON para impresión (base64)
        // Nota: para Pagos, no dependemos de comentarios_pdf, pero lo dejamos por compatibilidad
        $jsonArr = [
            'tipo_comprobante'      => $tipo,
            'tipo_nombre'           => $tipoNombre,

            // Receptor
            'receptor_rfc'          => (string)($cliente->rfc ?? ''),
            'receptor_razon_social' => (string)($cliente->razon_social ?? ''),

            // Folio/Serie
            'serie'                 => (string)($payload['serie'] ?? ''),
            'folio'                 => (string)($payload['folio'] ?? ''),

            // Facturas (si aplica)
            'comentarios_pdf'       => (string)($payload['comentarios_pdf'] ?? ''),

            // Pagos (opcional, por si tu plantilla lo lee)
            'fecha_pago'            => (string)($payload['fecha_pago'] ?? ''),
            'forma_pago_p'          => (string)($payload['forma_pago_p'] ?? ''),
            'moneda_p'              => (string)($payload['moneda_p'] ?? ''),
            'num_operacion'         => (string)($payload['num_operacion'] ?? ''),
        ];

        $jsonB64 = base64_encode(json_encode($jsonArr, JSON_UNESCAPED_UNICODE));

        $mp = new \App\Extensions\MultiPac\MultiPac();

        $resp = $mp->generatePDFV33([
            'xmlB64'     => $xmlB64,
            'plantilla'  => $plantilla,
            'json'       => $jsonB64,
            'logo'       => $logoB64,
        ]);

        // Si regresó string (SOAP raw), es error
        if (is_string($resp)) {
            throw new \RuntimeException('PAC PDF (SOAP): ' . mb_substr(strip_tags($resp), 0, 500));
        }

        $code = (string)($resp->code ?? $resp->codigo ?? $resp->CODIGO ?? '');
        $msg  = (string)($resp->message ?? $resp->mensaje ?? $resp->MENSAJE ?? '');

        $pdf = (string)($resp->pdf ?? $resp->PDF ?? '');

        // En FC1 el éxito es "210"
        if ($code !== '' && $code !== '210' && trim($pdf) === '') {
            $friendly = method_exists($this, 'traducirCodigoPac')
                ? $this->traducirCodigoPac('generarPDF', $code, $msg)
                : ($msg ?: "Código PAC: {$code}");

            throw new \RuntimeException($friendly);
        }

        if (trim($pdf) === '') {
            throw new \RuntimeException('PAC no devolvió PDF (base64) en generatePDFV33.');
        }

        return $pdf;
    }

}
