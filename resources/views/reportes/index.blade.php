<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reportes</h2>
            <p class="mt-1 text-sm text-gray-500">Filtra documentos por tipo y rango de fechas, y exporta la tabla a Excel o PDF.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" action="{{ route('reportes.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo de documento</label>
                        <select name="tipo" class="w-full rounded-md border-gray-300">
                            <option value="facturas" @selected(($filters['tipo'] ?? '') === 'facturas')>Facturas</option>
                            <option value="complementos" @selected(($filters['tipo'] ?? '') === 'complementos')>Complementos</option>
                            <option value="notas_credito" @selected(($filters['tipo'] ?? '') === 'notas_credito')>Notas de crédito</option>
                            <option value="canceladas" @selected(($filters['tipo'] ?? '') === 'canceladas')>Canceladas</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" value="{{ $filters['fecha_inicio'] ?? '' }}" class="w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha fin</label>
                        <input type="date" name="fecha_fin" value="{{ $filters['fecha_fin'] ?? '' }}" class="w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Estatus</label>
                        <select name="estatus" class="w-full rounded-md border-gray-300">
                            <option value="todos" @selected(($filters['estatus'] ?? '') === 'todos')>Todos</option>
                            <option value="vigentes" @selected(($filters['estatus'] ?? '') === 'vigentes')>Vigentes</option>
                            <option value="canceladas" @selected(($filters['estatus'] ?? '') === 'canceladas')>Canceladas</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Cliente</label>
                        <input type="text" name="cliente" value="{{ $filters['cliente'] ?? '' }}" placeholder="RFC o razón social" class="w-full rounded-md border-gray-300">
                    </div>

                    <div class="flex items-end gap-2">
                        <button class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">Generar</button>
                        <a href="{{ route('reportes.excel', ['tipo' => $filters['tipo'] ?? 'facturas', 'fecha_inicio' => $filters['fecha_inicio'] ?? '', 'fecha_fin' => $filters['fecha_fin'] ?? '', 'estatus' => $filters['estatus'] ?? 'todos', 'cliente' => $filters['cliente'] ?? '']) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm">Excel</a>
                        <a href="{{ route('reportes.pdf', ['tipo' => $filters['tipo'] ?? 'facturas', 'fecha_inicio' => $filters['fecha_inicio'] ?? '', 'fecha_fin' => $filters['fecha_fin'] ?? '', 'estatus' => $filters['estatus'] ?? 'todos', 'cliente' => $filters['cliente'] ?? '']) }}" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm">PDF</a>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="text-sm text-gray-500">{{ $rows->count() }} registros encontrados</div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">Tipo: {{ $filters['tipo_label'] ?? 'Facturas' }}</span>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">Fecha: {{ $filters['fecha_inicio'] ?? '' }} al {{ $filters['fecha_fin'] ?? '' }}</span>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">Estatus: {{ $filters['estatus_label'] ?? 'Todos' }}</span>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">Cliente: {{ $filters['cliente_label'] ?? 'Todos' }}</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Documento</th>
                                <th class="px-4 py-3 text-left">Serie/Folio</th>
                                <th class="px-4 py-3 text-left">UUID</th>
                                <th class="px-4 py-3 text-left">Cliente</th>
                                <th class="px-4 py-3 text-left">RFC</th>
                                <th class="px-4 py-3 text-left">Estatus</th>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row->documento }}</td>
                                    <td class="px-4 py-3">{{ trim(($row->serie ?? '') . '-' . ($row->folio ?? ''), '-') ?: ('#' . $row->id) }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->uuid ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $row->razon_social ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $row->rfc ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $row->estatus ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ !empty($row->fecha) ? \Carbon\Carbon::parse($row->fecha)->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format((float) ($row->total_calculado ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">No hay resultados para ese filtro.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
