<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Complemento de pago</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .h { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        .box { border:1px solid #ddd; padding:10px; margin-bottom:10px; }
        .muted { color:#666; }
    </style>
</head>
<body>
    @if (!empty($logoB64 ?? null))
        <div style="margin-bottom: 14px;">
            <img src="data:image/png;base64,{{ $logoB64 }}" alt="Logo" style="max-width: 110px; max-height: 110px;">
        </div>
    @endif

    @php($p20 = is_array($pagos20 ?? null) ? $pagos20 : ['totales' => [], 'pagos' => []])

    <div class="h">Complemento de pago</div>

    <div class="box">
        <div><b>UUID:</b> {{ $meta['uuid'] ?? '-' }}</div>
        <div><b>Serie/Folio:</b> {{ (($meta['serie'] ?? '') . ($meta['folio'] ?? '')) ?: '-' }}</div>
        <div><b>Fecha:</b> {{ $meta['fecha'] ?? '-' }}</div>
        <div><b>Total:</b> {{ $meta['total'] ?? '-' }} {{ $meta['moneda'] ?? '' }}</div>
    </div>

    <div class="box">
        <div><b>Totales Pagos</b></div>
        <div>Monto: {{ $p20['totales']['MontoTotalPagos'] ?? '0.00' }}</div>
        <div>Traslados Base IVA16: {{ $p20['totales']['TotalTrasladosBaseIVA16'] ?? '0.00' }}</div>
        <div>Traslados Imp. IVA16: {{ $p20['totales']['TotalTrasladosImpuestoIVA16'] ?? '0.00' }}</div>
    </div>

    @foreach(($p20['pagos'] ?? []) as $pago)
        <div class="box">
            <div><b>Pago:</b> {{ $pago['Monto'] ?? '' }} {{ $pago['MonedaP'] ?? '' }}</div>
            @foreach(($pago['doctos'] ?? []) as $doc)
                <div style="margin-top:8px;">
                    <div><b>Documento:</b> {{ $doc['IdDocumento'] ?? '' }}</div>
                    <div>ImpPagado: {{ $doc['ImpPagado'] ?? '' }} | ObjetoImpDR: {{ $doc['ObjetoImpDR'] ?? '' }}</div>
                    @foreach(($doc['traslados_dr'] ?? []) as $tdr)
                        <div class="muted">
                            BaseDR: {{ $tdr['BaseDR'] ?? '' }} |
                            ImpuestoDR: {{ $tdr['ImpuestoDR'] ?? '' }} |
                            Tasa: {{ $tdr['TasaOCuotaDR'] ?? '' }} |
                            ImporteDR: {{ $tdr['ImporteDR'] ?? '' }}
                        </div>
                    @endforeach
                </div>
            @endforeach
            @foreach(($pago['traslados_p'] ?? []) as $tp)
                <div class="muted" style="margin-top:6px;">
                    BaseP: {{ $tp['BaseP'] ?? '' }} |
                    ImpuestoP: {{ $tp['ImpuestoP'] ?? '' }} |
                    Tasa: {{ $tp['TasaOCuotaP'] ?? '' }} |
                    ImporteP: {{ $tp['ImporteP'] ?? '' }}
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="box">
        <div><b>Emisor:</b> {{ $parties['emisor_rfc'] ?? '-' }}</div>
        <div><b>Receptor:</b> {{ $parties['receptor_rfc'] ?? '-' }}</div>
        <div class="muted" style="margin-top:8px;">PDF regenerado por FC2.</div>
    </div>
</body>
</html>
