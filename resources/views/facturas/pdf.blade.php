<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>CFDI {{ ($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? '') }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { color: #222; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; line-height: 1.28; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .header td { padding-bottom: 8px; }
        .logo { max-height: 75px; max-width: 150px; }
        .title { color: #333; font-size: 17px; font-weight: bold; text-align: right; }
        .subtitle { color: #666; font-size: 8px; text-align: right; }
        .box { border: 1px solid #b8b8b8; margin-bottom: 7px; padding: 6px; }
        .section-title { background: #333; color: #fff; font-size: 9px; font-weight: bold; padding: 4px 6px; }
        .label { color: #666; font-size: 7.5px; }
        .value { font-weight: bold; }
        .data td { padding: 2px 4px; }
        .concepts { page-break-inside: auto; }
        .concepts thead { display: table-header-group; }
        .concepts tr { page-break-inside: avoid; page-break-after: auto; }
        .concepts th { background: #e8e8e8; border: 1px solid #aaa; font-size: 7px; padding: 4px 2px; }
        .concepts td { border: 1px solid #bbb; font-size: 7.2px; padding: 4px 2px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .tax { color: #555; font-size: 6.8px; margin-top: 3px; }
        .totals { margin-left: 58%; width: 42%; }
        .totals td { border-bottom: 1px solid #ddd; padding: 3px 4px; }
        .total { background: #333; color: #fff; font-size: 10px; font-weight: bold; }
        .fiscal td { padding: 3px 4px; }
        .wrap { overflow-wrap: anywhere; word-break: break-all; }
        .seal { font-size: 6.5px; }
        .qr { height: 125px; width: 125px; }
        .comments { font-size: 8px; white-space: pre-wrap; }
        .legend { font-size: 8px; font-weight: bold; margin-top: 7px; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:35%">
                @if (!empty($logoB64))
                    <img class="logo" src="data:image/png;base64,{{ $logoB64 }}" alt="Logo">
                @endif
            </td>
            <td style="width:65%">
                <div class="title">Comprobante Fiscal Digital por Internet</div>
                <div class="subtitle">CFDI {{ $cfdi['version'] ?? '4.0' }}</div>
                <table class="data" style="margin-top:4px">
                    <tr>
                        <td><span class="label">Serie / Folio</span><br><span class="value">{{ ($cfdi['serie'] ?? '') ?: '—' }} / {{ ($cfdi['folio'] ?? '') ?: '—' }}</span></td>
                        <td><span class="label">Tipo</span><br><span class="value">{{ $cfdi['tipo_comprobante'] ?? '—' }}</span></td>
                        <td><span class="label">Fecha emisión</span><br><span class="value">{{ $cfdi['fecha'] ?? '—' }}</span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-bottom:7px">
        <tr>
            <td style="width:49%; padding-right:4px">
                <div class="section-title">EMISOR</div>
                <div class="box">
                    <b>{{ $cfdi['emisor']['nombre'] ?? '—' }}</b><br>
                    RFC: {{ $cfdi['emisor']['rfc'] ?? '—' }}<br>
                    Régimen fiscal: {{ $cfdi['emisor']['regimen_fiscal'] ?? '—' }}<br>
                    Lugar de expedición: {{ $cfdi['lugar_expedicion'] ?? '—' }}
                </div>
            </td>
            <td style="width:51%; padding-left:4px">
                <div class="section-title">RECEPTOR</div>
                <div class="box">
                    <b>{{ $cfdi['receptor']['nombre'] ?? '—' }}</b><br>
                    RFC: {{ $cfdi['receptor']['rfc'] ?? '—' }}<br>
                    Domicilio fiscal: {{ $cfdi['receptor']['domicilio_fiscal'] ?? '—' }}<br>
                    Régimen fiscal: {{ $cfdi['receptor']['regimen_fiscal'] ?? '—' }}<br>
                    Uso CFDI: {{ $cfdi['receptor']['uso_cfdi'] ?? '—' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="data box">
        <tr>
            <td><span class="label">Moneda</span><br><span class="value">{{ $cfdi['moneda'] ?? '—' }}</span></td>
            <td><span class="label">Forma de pago</span><br><span class="value">{{ $cfdi['forma_pago'] ?? '—' }}</span></td>
            <td><span class="label">Método de pago</span><br><span class="value">{{ $cfdi['metodo_pago'] ?? '—' }}</span></td>
            <td><span class="label">Exportación</span><br><span class="value">{{ $cfdi['exportacion'] ?? '—' }}</span></td>
        </tr>
    </table>

    <div class="section-title">CONCEPTOS</div>
    <table class="concepts">
        <thead>
            <tr>
                <th style="width:7%">Cant.</th>
                <th style="width:10%">Unidad</th>
                <th style="width:12%">Clave P/S</th>
                <th style="width:8%">Objeto imp.</th>
                <th style="width:31%">Descripción</th>
                <th style="width:11%">V. unitario</th>
                <th style="width:10%">Descuento</th>
                <th style="width:11%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cfdi['conceptos'] as $concepto)
                <tr>
                    <td class="right">{{ $concepto['cantidad'] }}</td>
                    <td>{{ $concepto['clave_unidad'] }}{{ $concepto['unidad'] !== '' ? ' - '.$concepto['unidad'] : '' }}</td>
                    <td>{{ $concepto['clave_prod_serv'] }}{{ $concepto['no_identificacion'] !== '' ? ' / '.$concepto['no_identificacion'] : '' }}</td>
                    <td class="center">{{ $concepto['objeto_imp'] }}</td>
                    <td>
                        {{ $concepto['descripcion'] }}
                        @foreach ($concepto['traslados'] as $tax)
                            <div class="tax">Traslado {{ $tax['impuesto'] }} | Base {{ $tax['base'] }} | {{ $tax['tipo_factor'] }} {{ $tax['tasa_cuota'] }} | Importe {{ $tax['importe'] }}</div>
                        @endforeach
                        @foreach ($concepto['retenciones'] as $tax)
                            <div class="tax">Retención {{ $tax['impuesto'] }} | Base {{ $tax['base'] }} | {{ $tax['tipo_factor'] }} {{ $tax['tasa_cuota'] }} | Importe {{ $tax['importe'] }}</div>
                        @endforeach
                    </td>
                    <td class="right">{{ number_format((float) $concepto['valor_unitario'], 2) }}</td>
                    <td class="right">{{ $concepto['descuento'] !== '' ? number_format((float) $concepto['descuento'], 2) : '—' }}</td>
                    <td class="right">{{ number_format((float) $concepto['importe'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top:8px">
        <tr>
            <td style="width:55%; padding-right:10px">
                @if (!empty($cfdi['impuestos']['traslados']) || !empty($cfdi['impuestos']['retenciones']))
                    <div class="section-title">RESUMEN DE IMPUESTOS</div>
                    <div class="box">
                        @foreach ($cfdi['impuestos']['traslados'] as $tax)
                            Traslado {{ $tax['impuesto'] }} | {{ $tax['tipo_factor'] }} {{ $tax['tasa_cuota'] }} | Importe {{ $tax['importe'] }}<br>
                        @endforeach
                        @foreach ($cfdi['impuestos']['retenciones'] as $tax)
                            Retención {{ $tax['impuesto'] }} | Importe {{ $tax['importe'] }}<br>
                        @endforeach
                    </div>
                @endif
            </td>
            <td style="width:45%">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="right">{{ number_format((float) ($cfdi['subtotal'] ?? 0), 2) }}</td></tr>
                    @if (($cfdi['descuento'] ?? '') !== '')
                        <tr><td>Descuento</td><td class="right">{{ number_format((float) $cfdi['descuento'], 2) }}</td></tr>
                    @endif
                    @if (($cfdi['impuestos']['total_trasladados'] ?? '') !== '')
                        <tr><td>Impuestos trasladados</td><td class="right">{{ number_format((float) $cfdi['impuestos']['total_trasladados'], 2) }}</td></tr>
                    @endif
                    @if (($cfdi['impuestos']['total_retenidos'] ?? '') !== '')
                        <tr><td>Impuestos retenidos</td><td class="right">{{ number_format((float) $cfdi['impuestos']['total_retenidos'], 2) }}</td></tr>
                    @endif
                    <tr class="total"><td>Total {{ $cfdi['moneda'] ?? '' }}</td><td class="right">{{ number_format((float) ($cfdi['total'] ?? 0), 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title" style="margin-top:8px">TIMBRE FISCAL DIGITAL</div>
    <div class="box">
        <table class="fiscal">
            <tr>
                <td style="width:76%">
                    <b>UUID:</b> {{ $cfdi['timbre']['uuid'] ?? '—' }}<br>
                    <b>Fecha de timbrado:</b> {{ $cfdi['timbre']['fecha_timbrado'] ?? '—' }}<br>
                    <b>RFC PAC:</b> {{ $cfdi['timbre']['rfc_prov_certif'] ?? '—' }}<br>
                    <b>No. certificado emisor:</b> {{ $cfdi['no_certificado'] ?? '—' }}<br>
                    <b>No. certificado SAT:</b> {{ $cfdi['timbre']['no_certificado_sat'] ?? '—' }}
                </td>
                <td style="width:24%; text-align:right">
                    @if (!empty($cfdi['qr_data_uri']))
                        <img class="qr" src="{{ $cfdi['qr_data_uri'] }}" alt="QR SAT">
                    @endif
                </td>
            </tr>
        </table>

        <div class="label">Sello digital del CFDI</div>
        <div class="wrap seal">{{ $cfdi['timbre']['sello_cfdi'] ?? $cfdi['sello'] ?? '—' }}</div>
        <div class="label" style="margin-top:5px">Sello digital del SAT</div>
        <div class="wrap seal">{{ $cfdi['timbre']['sello_sat'] ?? '—' }}</div>
        <div class="label" style="margin-top:5px">Cadena original del complemento de certificación digital del SAT</div>
        <div class="wrap seal">{{ $cfdi['cadena_original_tfd'] ?: '—' }}</div>
    </div>

    @if (trim((string) ($comentariosPdf ?? '')) !== '')
        <div class="section-title">COMENTARIOS</div>
        <div class="box comments">{{ $comentariosPdf }}</div>
    @endif

    <div class="legend">Este documento es una representación impresa de un CFDI</div>
</body>
</html>
