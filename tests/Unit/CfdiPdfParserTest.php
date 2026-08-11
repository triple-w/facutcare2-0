<?php

namespace Tests\Unit;

use App\Support\CfdiPdfParser;
use PHPUnit\Framework\TestCase;

class CfdiPdfParserTest extends TestCase
{
    public function test_it_extracts_invoice_and_stamp_data_from_cfdi_40(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Serie="A" Folio="118443" Fecha="2026-08-11T10:00:00" Sello="SELLO12345678" FormaPago="03" NoCertificado="30001000000500003456" SubTotal="100.00" Moneda="MXN" Total="116.00" TipoDeComprobante="I" Exportacion="01" MetodoPago="PUE" LugarExpedicion="72000">
  <cfdi:Emisor Rfc="AAA010101AAA" Nombre="EMISOR SA DE CV" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="XAXX010101000" Nombre="PUBLICO EN GENERAL" DomicilioFiscalReceptor="72000" RegimenFiscalReceptor="616" UsoCFDI="S01"/>
  <cfdi:Conceptos>
    <cfdi:Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="H87" Unidad="Pieza" Descripcion="Producto" ValorUnitario="100.00" Importe="100.00" ObjetoImp="02">
      <cfdi:Impuestos><cfdi:Traslados><cfdi:Traslado Base="100.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="16.00"/></cfdi:Traslados></cfdi:Impuestos>
    </cfdi:Concepto>
  </cfdi:Conceptos>
  <cfdi:Impuestos TotalImpuestosTrasladados="16.00"><cfdi:Traslados><cfdi:Traslado Base="100.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="16.00"/></cfdi:Traslados></cfdi:Impuestos>
  <cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="11111111-2222-3333-4444-555555555555" FechaTimbrado="2026-08-11T10:00:01" RfcProvCertif="SAT970701NN3" SelloCFD="SELLO12345678" NoCertificadoSAT="00001000000504465028" SelloSAT="SELLOSAT"/></cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $data = (new CfdiPdfParser())->parse($xml);

        $this->assertSame('A', $data['serie']);
        $this->assertSame('118443', $data['folio']);
        $this->assertSame('AAA010101AAA', $data['emisor']['rfc']);
        $this->assertSame('XAXX010101000', $data['receptor']['rfc']);
        $this->assertSame('Producto', $data['conceptos'][0]['descripcion']);
        $this->assertSame('16.00', $data['conceptos'][0]['traslados'][0]['importe']);
        $this->assertSame('16.00', $data['impuestos']['total_trasladados']);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $data['timbre']['uuid']);
        $this->assertSame(
            '||1.1|11111111-2222-3333-4444-555555555555|2026-08-11T10:00:01|SAT970701NN3|SELLO12345678|00001000000504465028||',
            $data['cadena_original_tfd']
        );
        $this->assertStringContainsString('fe=12345678', $data['qr_url']);
    }
}
