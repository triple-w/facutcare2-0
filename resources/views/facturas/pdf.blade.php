<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .h { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        .box { border:1px solid #ddd; padding:10px; margin-bottom:10px; }
        .muted { color:#666; }
    </style>
</head>
<body>
    <div class="h">Factura</div>

    <div class="box">
        <div><b>UUID:</b> {{ $meta['uuid'] ?? $factura->uuid ?? '—' }}</div>
        <div><b>Serie/Folio:</b> {{ (($meta['serie'] ?? '') . ($meta['folio'] ?? '')) ?: '—' }}</div>
        <div><b>Fecha:</b> {{ $meta['fecha'] ?? $factura->fecha ?? '—' }}</div>
        <div><b>Total:</b> {{ $meta['total'] ?? '—' }} {{ $meta['moneda'] ?? '' }}</div>
    </div>

    <div class="box">
        <div><b>Emisor:</b> {{ $meta['emisor_rfc'] ?? '—' }} {{ $meta['emisor_nombre'] ? ' | '.$meta['emisor_nombre'] : '' }}</div>
        <div><b>Receptor:</b> {{ $meta['receptor_rfc'] ?? '—' }} {{ $meta['receptor_nombre'] ? ' | '.$meta['receptor_nombre'] : '' }}</div>
        <div class="muted" style="margin-top:8px;">PDF regenerado por FC2 (formato simple).</div>
    </div>
</body>
</html>
