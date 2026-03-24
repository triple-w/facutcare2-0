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

    <div class="h">Complemento de pago</div>

    <div class="box">
        <div><b>UUID:</b> {{ $meta['uuid'] ?? '—' }}</div>
        <div><b>Serie/Folio:</b> {{ (($meta['serie'] ?? '') . ($meta['folio'] ?? '')) ?: '—' }}</div>
        <div><b>Fecha:</b> {{ $meta['fecha'] ?? '—' }}</div>
        <div><b>Total:</b> {{ $meta['total'] ?? '—' }} {{ $meta['moneda'] ?? '' }}</div>
    </div>

    <div class="box">
        <div><b>Emisor:</b> {{ $parties['emisor_rfc'] ?? '—' }}</div>
        <div><b>Receptor:</b> {{ $parties['receptor_rfc'] ?? '—' }}</div>
        <div class="muted" style="margin-top:8px;">PDF regenerado por FC2 (formato simple).</div>
    </div>
</body>
</html>
