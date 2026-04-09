<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 18px; margin-bottom: 6px; }
        p { margin: 0 0 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Reporte de {{ $filters['tipo_label'] ?? str_replace('_', ' ', $filters['tipo'] ?? 'documentos') }}</h1>
    <p>Rango: {{ $filters['fecha_inicio'] ?? '' }} al {{ $filters['fecha_fin'] ?? '' }}</p>
    <p>Estatus: {{ $filters['estatus_label'] ?? 'Todos' }} | Cliente filtro: {{ $filters['cliente_label'] ?? 'Todos' }}</p>
    <p>RFC cliente: {{ $summary['cliente_rfc'] ?? '—' }} | Razón social: {{ $summary['cliente_razon_social'] ?? '—' }}</p>
    <p>Fecha del reporte: {{ $summary['fecha_reporte'] ?? '—' }}</p>
    <p>Totales | Ingresos: ${{ number_format((float)($summary['totales']['ingresos'] ?? 0), 2) }} | Egresos: ${{ number_format((float)($summary['totales']['egresos'] ?? 0), 2) }} | Pagos: ${{ number_format((float)($summary['totales']['pagos'] ?? 0), 2) }}</p>

    <table>
        <thead>
            <tr>
                <th>Documento</th>
                <th>Serie/Folio</th>
                <th>UUID</th>
                <th>Cliente</th>
                <th>RFC</th>
                <th>Estatus</th>
                <th>Fecha</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->documento }}</td>
                    <td>{{ trim(($row->serie ?? '') . '-' . ($row->folio ?? ''), '-') ?: ('#' . $row->id) }}</td>
                    <td>{{ $row->uuid ?? '—' }}</td>
                    <td>{{ $row->razon_social ?? '—' }}</td>
                    <td>{{ $row->rfc ?? '—' }}</td>
                    <td>{{ $row->estatus ?? '—' }}</td>
                    <td>{{ !empty($row->fecha) ? \Carbon\Carbon::parse($row->fecha)->format('d/m/Y H:i') : '—' }}</td>
                    <td class="num">${{ number_format((float) ($row->total_calculado ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
