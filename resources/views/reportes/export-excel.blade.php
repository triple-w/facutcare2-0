<table border="1">
    <tr>
        <td colspan="8">Tipo: {{ $filters['tipo_label'] ?? str_replace('_', ' ', $filters['tipo'] ?? 'documentos') }}</td>
    </tr>
    <tr>
        <td colspan="8">Fecha: {{ $filters['fecha_inicio'] ?? '' }} al {{ $filters['fecha_fin'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="8">Estatus: {{ $filters['estatus_label'] ?? 'Todos' }} | Cliente filtro: {{ $filters['cliente_label'] ?? 'Todos' }}</td>
    </tr>
    <tr>
        <td colspan="8">RFC cliente: {{ $summary['cliente_rfc'] ?? '—' }} | Razón social: {{ $summary['cliente_razon_social'] ?? '—' }}</td>
    </tr>
    <tr>
        <td colspan="8">Fecha del reporte: {{ $summary['fecha_reporte'] ?? '—' }}</td>
    </tr>
    <tr>
        <td colspan="8">Totales | Ingresos: {{ number_format((float)($summary['totales']['ingresos'] ?? 0), 2, '.', '') }} | Egresos: {{ number_format((float)($summary['totales']['egresos'] ?? 0), 2, '.', '') }} | Pagos: {{ number_format((float)($summary['totales']['pagos'] ?? 0), 2, '.', '') }}</td>
    </tr>
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
                <td>{{ number_format((float) ($row->total_calculado ?? 0), 2, '.', '') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
