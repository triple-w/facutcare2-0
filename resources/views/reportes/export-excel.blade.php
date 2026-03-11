<table border="1">
    <tr>
        <td colspan="8">Tipo: {{ $filters['tipo_label'] ?? str_replace('_', ' ', $filters['tipo'] ?? 'documentos') }}</td>
    </tr>
    <tr>
        <td colspan="8">Fecha: {{ $filters['fecha_inicio'] ?? '' }} al {{ $filters['fecha_fin'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="8">Estatus: {{ $filters['estatus_label'] ?? 'Todos' }} | Cliente: {{ $filters['cliente_label'] ?? 'Todos' }}</td>
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
