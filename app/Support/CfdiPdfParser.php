<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class CfdiPdfParser
{
    public function parse(string $xml): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            throw new \RuntimeException('El XML timbrado no es válido.');
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $comprobante = $xp->query('/cfdi:Comprobante')->item(0);
        if (!$comprobante instanceof \DOMElement) {
            throw new \RuntimeException('El XML no contiene un Comprobante CFDI 4.0.');
        }

        $emisor = $xp->query('./cfdi:Emisor', $comprobante)->item(0);
        $receptor = $xp->query('./cfdi:Receptor', $comprobante)->item(0);
        $timbre = $xp->query('./cfdi:Complemento/tfd:TimbreFiscalDigital', $comprobante)->item(0);

        $data = [
            'version' => $this->attribute($comprobante, 'Version'),
            'serie' => $this->attribute($comprobante, 'Serie'),
            'folio' => $this->attribute($comprobante, 'Folio'),
            'fecha' => $this->attribute($comprobante, 'Fecha'),
            'tipo_comprobante' => $this->attribute($comprobante, 'TipoDeComprobante'),
            'moneda' => $this->attribute($comprobante, 'Moneda'),
            'forma_pago' => $this->attribute($comprobante, 'FormaPago'),
            'metodo_pago' => $this->attribute($comprobante, 'MetodoPago'),
            'lugar_expedicion' => $this->attribute($comprobante, 'LugarExpedicion'),
            'exportacion' => $this->attribute($comprobante, 'Exportacion'),
            'no_certificado' => $this->attribute($comprobante, 'NoCertificado'),
            'subtotal' => $this->attribute($comprobante, 'SubTotal'),
            'descuento' => $this->attribute($comprobante, 'Descuento'),
            'total' => $this->attribute($comprobante, 'Total'),
            'sello' => $this->attribute($comprobante, 'Sello'),
            'emisor' => $this->party($emisor, false),
            'receptor' => $this->party($receptor, true),
            'conceptos' => [],
            'impuestos' => $this->globalTaxes($xp, $comprobante),
            'timbre' => $this->timbre($timbre),
            'cadena_original_tfd' => '',
            'qr_url' => '',
            'qr_data_uri' => null,
        ];

        foreach ($xp->query('./cfdi:Conceptos/cfdi:Concepto', $comprobante) as $concepto) {
            if ($concepto instanceof \DOMElement) {
                $data['conceptos'][] = $this->concept($xp, $concepto);
            }
        }

        $data['cadena_original_tfd'] = $this->tfdOriginalString($data['timbre']);
        $data['qr_url'] = $this->satVerificationUrl($data);
        $data['qr_data_uri'] = $this->qrDataUri($data['qr_url']);

        return $data;
    }

    private function party(?\DOMNode $node, bool $receiver): array
    {
        if (!$node instanceof \DOMElement) {
            return [];
        }

        $data = [
            'rfc' => $this->attribute($node, 'Rfc'),
            'nombre' => $this->attribute($node, 'Nombre'),
            'regimen_fiscal' => $this->attribute($node, 'RegimenFiscal'),
        ];

        if ($receiver) {
            $data['domicilio_fiscal'] = $this->attribute($node, 'DomicilioFiscalReceptor');
            $data['regimen_fiscal'] = $this->attribute($node, 'RegimenFiscalReceptor');
            $data['uso_cfdi'] = $this->attribute($node, 'UsoCFDI');
            $data['residencia_fiscal'] = $this->attribute($node, 'ResidenciaFiscal');
            $data['num_reg_id_trib'] = $this->attribute($node, 'NumRegIdTrib');
        }

        return $data;
    }

    private function concept(\DOMXPath $xp, \DOMElement $node): array
    {
        return [
            'cantidad' => $this->attribute($node, 'Cantidad'),
            'clave_unidad' => $this->attribute($node, 'ClaveUnidad'),
            'unidad' => $this->attribute($node, 'Unidad'),
            'clave_prod_serv' => $this->attribute($node, 'ClaveProdServ'),
            'no_identificacion' => $this->attribute($node, 'NoIdentificacion'),
            'objeto_imp' => $this->attribute($node, 'ObjetoImp'),
            'descripcion' => $this->attribute($node, 'Descripcion'),
            'valor_unitario' => $this->attribute($node, 'ValorUnitario'),
            'importe' => $this->attribute($node, 'Importe'),
            'descuento' => $this->attribute($node, 'Descuento'),
            'traslados' => $this->taxNodes($xp, './cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', $node),
            'retenciones' => $this->taxNodes($xp, './cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion', $node),
        ];
    }

    private function globalTaxes(\DOMXPath $xp, \DOMElement $comprobante): array
    {
        $node = $xp->query('./cfdi:Impuestos', $comprobante)->item(0);

        return [
            'total_trasladados' => $node instanceof \DOMElement ? $this->attribute($node, 'TotalImpuestosTrasladados') : '',
            'total_retenidos' => $node instanceof \DOMElement ? $this->attribute($node, 'TotalImpuestosRetenidos') : '',
            'traslados' => $this->taxNodes($xp, './cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', $comprobante),
            'retenciones' => $this->taxNodes($xp, './cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion', $comprobante),
        ];
    }

    private function taxNodes(\DOMXPath $xp, string $query, \DOMElement $context): array
    {
        $taxes = [];
        foreach ($xp->query($query, $context) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $taxes[] = [
                'base' => $this->attribute($node, 'Base'),
                'impuesto' => $this->attribute($node, 'Impuesto'),
                'tipo_factor' => $this->attribute($node, 'TipoFactor'),
                'tasa_cuota' => $this->attribute($node, 'TasaOCuota'),
                'importe' => $this->attribute($node, 'Importe'),
            ];
        }

        return $taxes;
    }

    private function timbre(?\DOMNode $node): array
    {
        if (!$node instanceof \DOMElement) {
            return [];
        }

        return [
            'version' => $this->attribute($node, 'Version'),
            'uuid' => $this->attribute($node, 'UUID'),
            'fecha_timbrado' => $this->attribute($node, 'FechaTimbrado'),
            'rfc_prov_certif' => $this->attribute($node, 'RfcProvCertif'),
            'leyenda' => $this->attribute($node, 'Leyenda'),
            'sello_cfdi' => $this->attribute($node, 'SelloCFD'),
            'no_certificado_sat' => $this->attribute($node, 'NoCertificadoSAT'),
            'sello_sat' => $this->attribute($node, 'SelloSAT'),
        ];
    }

    private function tfdOriginalString(array $timbre): string
    {
        if (($timbre['uuid'] ?? '') === '') {
            return '';
        }

        $values = [
            $timbre['version'] ?? '',
            $timbre['uuid'] ?? '',
            $timbre['fecha_timbrado'] ?? '',
            $timbre['rfc_prov_certif'] ?? '',
        ];

        if (($timbre['leyenda'] ?? '') !== '') {
            $values[] = $timbre['leyenda'];
        }

        $values[] = $timbre['sello_cfdi'] ?? '';
        $values[] = $timbre['no_certificado_sat'] ?? '';

        return '||' . implode('|', $values) . '||';
    }

    private function satVerificationUrl(array $data): string
    {
        $uuid = (string) ($data['timbre']['uuid'] ?? '');
        $emisor = (string) ($data['emisor']['rfc'] ?? '');
        $receptor = (string) ($data['receptor']['rfc'] ?? '');
        $sello = (string) ($data['timbre']['sello_cfdi'] ?? '');

        if ($uuid === '' || $emisor === '' || $receptor === '' || $sello === '') {
            return '';
        }

        $total = number_format((float) ($data['total'] ?? 0), 6, '.', '');

        return 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?'
            . http_build_query([
                'id' => $uuid,
                're' => $emisor,
                'rr' => $receptor,
                'tt' => $total,
                'fe' => substr($sello, -8),
            ], '', '&', PHP_QUERY_RFC3986);
    }

    private function qrDataUri(string $url): ?string
    {
        if ($url === '' || !class_exists(Writer::class)) {
            return null;
        }

        try {
            $renderer = new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd());
            $svg = (new Writer($renderer))->writeString($url);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function attribute(\DOMElement $node, string $name): string
    {
        return trim($node->getAttribute($name));
    }
}
