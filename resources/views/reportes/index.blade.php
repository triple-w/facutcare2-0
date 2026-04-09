<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reportes</h2>
                <p class="mt-1 text-sm text-gray-500">Filtra documentos por tipo y rango de fechas, y exporta la tabla a Excel o PDF.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-2 text-sm">
                <div class="rounded-lg bg-gray-100 px-3 py-2">
                    <div class="text-xs uppercase tracking-wide text-gray-500">RFC cliente</div>
                    <div class="font-semibold text-gray-800">{{ $summary['cliente_rfc'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg bg-gray-100 px-3 py-2">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Razón social</div>
                    <div class="font-semibold text-gray-800">{{ $summary['cliente_razon_social'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg bg-gray-100 px-3 py-2">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Fecha reporte</div>
                    <div class="font-semibold text-gray-800">{{ $summary['fecha_reporte'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg bg-emerald-50 px-3 py-2">
                    <div class="text-xs uppercase tracking-wide text-emerald-700">Ingresos</div>
                    <div class="font-semibold text-emerald-900">${{ number_format((float)($summary['totales']['ingresos'] ?? 0), 2) }}</div>
                </div>
                <div class="rounded-lg bg-amber-50 px-3 py-2">
                    <div class="text-xs uppercase tracking-wide text-amber-700">Egresos</div>
                    <div class="font-semibold text-amber-900">${{ number_format((float)($summary['totales']['egresos'] ?? 0), 2) }}</div>
                </div>
                <div class="rounded-lg bg-sky-50 px-3 py-2">
                    <div class="text-xs uppercase tracking-wide text-sky-700">Pagos</div>
                    <div class="font-semibold text-sky-900">${{ number_format((float)($summary['totales']['pagos'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @php
                    $clientesJson = ($clientes ?? collect())->values()->all();
                @endphp
                <form method="GET" action="{{ route('reportes.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4"
                      x-data="{
                        clientes: @js($clientesJson),
                        tipo: @js($filters['tipo'] ?? 'facturas'),
                        fechaInicio: @js($filters['fecha_inicio'] ?? ''),
                        fechaFin: @js($filters['fecha_fin'] ?? ''),
                        estatus: @js($filters['estatus'] ?? 'todos'),
                        clienteQuery: @js($filters['cliente'] ?? ''),
                        open: false,
                        get filteredClientes() {
                          const q = (this.clienteQuery || '').trim().toLowerCase();
                          if (!q) return this.clientes.slice(0, 12);
                          return this.clientes.filter(c => (`${c.razon_social} ${c.rfc}`).toLowerCase().includes(q)).slice(0, 12);
                        },
                        exportUrl(base) {
                          const url = new URL(base, window.location.origin);
                          url.searchParams.set('tipo', this.tipo || 'facturas');
                          url.searchParams.set('fecha_inicio', this.fechaInicio || '');
                          url.searchParams.set('fecha_fin', this.fechaFin || '');
                          url.searchParams.set('estatus', this.estatus || 'todos');
                          url.searchParams.set('cliente', this.clienteQuery || '');
                          return url.toString();
                        },
                        selectCliente(cliente) {
                          this.clienteQuery = cliente.label || `${cliente.razon_social} - ${cliente.rfc}`;
                          this.open = false;
                        }
                      }">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo de documento</label>
                        <select name="tipo" x-model="tipo" class="w-full rounded-md border-gray-300">
                            <option value="facturas" @selected(($filters['tipo'] ?? '') === 'facturas')>Facturas</option>
                            <option value="complementos" @selected(($filters['tipo'] ?? '') === 'complementos')>Complementos</option>
                            <option value="notas_credito" @selected(($filters['tipo'] ?? '') === 'notas_credito')>Notas de crédito</option>
                            <option value="canceladas" @selected(($filters['tipo'] ?? '') === 'canceladas')>Canceladas</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" x-model="fechaInicio" class="w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha fin</label>
                        <input type="date" name="fecha_fin" x-model="fechaFin" class="w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Estatus</label>
                        <select name="estatus" x-model="estatus" class="w-full rounded-md border-gray-300">
                            <option value="todos" @selected(($filters['estatus'] ?? '') === 'todos')>Todos</option>
                            <option value="vigentes" @selected(($filters['estatus'] ?? '') === 'vigentes')>Vigentes</option>
                            <option value="canceladas" @selected(($filters['estatus'] ?? '') === 'canceladas')>Canceladas</option>
                        </select>
                    </div>

                    <div class="relative" @click.outside="open = false">
                        <label class="block text-sm font-medium mb-1">Cliente</label>
                        <input type="text"
                               name="cliente"
                               x-model="clienteQuery"
                               @focus="open = true"
                               @input="open = true"
                               @keydown.escape="open = false"
                               autocomplete="off"
                               placeholder="RFC o razón social"
                               class="w-full rounded-md border-gray-300">
                        <div x-show="open && filteredClientes.length" x-transition class="absolute z-20 mt-1 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg" style="display:none;">
                            <ul class="max-h-64 overflow-auto py-1">
                                <template x-for="cliente in filteredClientes" :key="cliente.id">
                                    <li>
                                        <button type="button"
                                                class="block w-full px-3 py-2 text-left hover:bg-gray-50"
                                                @click="selectCliente(cliente)">
                                            <div class="text-sm font-medium text-gray-900" x-text="cliente.razon_social || '—'"></div>
                                            <div class="text-xs text-gray-500" x-text="cliente.rfc || ''"></div>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-2 md:col-span-5">
                        <button class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800">Generar</button>
                        <a :href="exportUrl(@js(route('reportes.excel')))" class="inline-flex items-center justify-center rounded-md border border-green-700 bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700">Descargar Excel</a>
                        <a :href="exportUrl(@js(route('reportes.pdf')))" class="inline-flex items-center justify-center rounded-md border border-red-700 bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-red-700">Descargar PDF</a>
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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-6 py-5 border-b border-gray-100 bg-gray-50/70">
                    <div class="rounded-lg border border-emerald-100 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-emerald-700">Total ingresos</div>
                        <div class="mt-1 text-2xl font-semibold text-emerald-900">${{ number_format((float)($summary['totales']['ingresos'] ?? 0), 2) }}</div>
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-amber-700">Total egresos</div>
                        <div class="mt-1 text-2xl font-semibold text-amber-900">${{ number_format((float)($summary['totales']['egresos'] ?? 0), 2) }}</div>
                    </div>
                    <div class="rounded-lg border border-sky-100 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-sky-700">Total pagos</div>
                        <div class="mt-1 text-2xl font-semibold text-sky-900">${{ number_format((float)($summary['totales']['pagos'] ?? 0), 2) }}</div>
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
