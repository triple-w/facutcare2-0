<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-gray-500">Factura</div>
                <div class="text-xl font-semibold">
                    {{ ($cfdi['serie'] ?? '') }}{{ ($cfdi['folio'] ?? '') ? ''.$cfdi['folio'] : '' }}
                    @if(empty($cfdi['serie']) && empty($cfdi['folio']))
                        #{{ $factura->id }}
                    @endif
                </div>
                    @if(session('ok'))
                    <div class="p-3 rounded bg-green-50 text-green-700 text-sm mb-4">
                        {{ session('ok') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="p-3 rounded bg-red-50 text-red-700 text-sm mb-4">
                        {{ session('error') }}
                    </div>
                @endif
                <div class="text-sm text-gray-500 mt-1">
                    UUID: <span class="font-mono">{{ $cfdi['uuid'] ?? ($factura->uuid ?? '—') }}</span>
                </div>
            </div>

            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('facturas.index') }}"
                   class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">
                    Volver
                </a>

                <a href="{{ route('facturas.xml', $factura->id) }}"
                   class="px-3 py-2 rounded-lg bg-gray-900 text-white hover:opacity-90">
                    XML
                </a>

                <a href="{{ route('facturas.pdf', $factura->id) }}"
                   class="px-3 py-2 rounded-lg bg-gray-900 text-white hover:opacity-90">
                    PDF
                </a>

                @if(!empty($factura->acuse))
                    <a href="{{ route('facturas.acuse', $factura->id) }}"
                       class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">
                        Acuse
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        $r = $factura ?? (object)[];

        $fmtDir = function($o) {
            $o = $o ?? (object)[];
            $calle = trim(($o->calle ?? '').' '.($o->no_ext ?? ''));
            $int = !empty($o->no_int) ? (' Int '.$o->no_int) : '';
            $col = $o->colonia ?? '';
            $mun = $o->municipio ?? '';
            $edo = $o->estado ?? '';
            $cp  = $o->codigo_postal ?? '';
            $pais = $o->pais ?? '';

            $parts = array_filter([
                trim($calle.$int) ?: null,
                $col ?: null,
                $mun ?: null,
                $edo ?: null,
                $cp ? ('CP '.$cp) : null,
                $pais ?: null,
            ]);

            return trim(implode(', ', $parts));
        };

        $dirReceptor = $fmtDir($r);

        $tSubtotal = (float)($totales['subtotal'] ?? 0);
        $tDescuento = (float)($totales['descuento'] ?? 0);
        $tIva = (float)($totales['iva'] ?? 0);
        $tTotal = (float)($totales['total'] ?? 0);
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Datos del comprobante --}}
            <div class="p-4 rounded-xl border bg-white space-y-3">
                <div class="text-sm font-semibold text-gray-700">Datos del comprobante</div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                        <div class="text-gray-500">Estatus</div>
                        @php $status = strtoupper((string)($factura->estatus ?? '')); @endphp
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                            {{ $status === 'TIMBRADA' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $factura->estatus ?? '—' }}
                        </span>
                    </div>

                    <div>
                        <div class="text-gray-500">Fecha</div>
                        <div class="font-medium">
                            {{ $factura->fecha_factura ?? $factura->fecha ?? ($cfdi['fecha'] ?? '—') }}
                        </div>
                    </div>

                    <div>
                        <div class="text-gray-500">Tipo comprobante</div>
                        <div class="font-medium">{{ $cfdi['tipo_comprobante'] ?? ($factura->tipo_comprobante ?? '—') }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Método pago</div>
                        <div class="font-medium">{{ $cfdi['metodo_pago'] ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Forma pago</div>
                        <div class="font-medium">{{ $cfdi['forma_pago'] ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Moneda</div>
                        <div class="font-medium">{{ $cfdi['moneda'] ?? 'MXN' }}</div>
                    </div>
                </div>
            </div>

            {{-- Receptor (sin Emisor) --}}
            <div class="p-4 rounded-xl border bg-white space-y-2">
                <div class="text-sm font-semibold text-gray-700">Receptor</div>
                <div class="text-sm">
                    <div class="font-medium">{{ $r->razon_social ?? '—' }}</div>
                    <div class="text-gray-600">RFC: <span class="font-mono">{{ $r->rfc ?? '—' }}</span></div>
                    <div class="text-gray-600">{{ $dirReceptor !== '' ? $dirReceptor : '—' }}</div>
                    @if(!empty($r->telefono))
                        <div class="text-gray-600">Tel: {{ $r->telefono }}</div>
                    @endif
                </div>
            </div>

            {{-- Conceptos --}}
            <div class="p-4 rounded-xl border bg-white">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-sm font-semibold text-gray-700">Conceptos</div>
                    <div class="text-sm text-gray-500">Total: {{ $detalles->count() }}</div>
                </div>

                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr class="border-b">
                                <th class="py-2 pr-3">Cant.</th>
                                <th class="py-2 pr-3">Unidad</th>
                                <th class="py-2 pr-3">Descripción</th>
                                <th class="py-2 pr-3 text-right">P. Unit</th>
                                <th class="py-2 pr-3 text-right">Importe</th>
                                <th class="py-2 pr-0 text-right">IVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($detalles as $d)
                                <tr class="align-top">
                                    <td class="py-2 pr-3">{{ $d->cantidad ?? 0 }}</td>
                                    <td class="py-2 pr-3">{{ $d->unidad ?? '—' }}</td>
                                    <td class="py-2 pr-3">
                                        <div class="font-medium text-gray-900">{{ $d->descripcion ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $d->numero_clave_prod ?? $d->clave ?? '' }}
                                        </div>
                                    </td>
                                    <td class="py-2 pr-3 text-right">$ {{ number_format((float)($d->precio ?? 0), 2) }}</td>
                                    <td class="py-2 pr-3 text-right">$ {{ number_format((float)($d->importe ?? 0), 2) }}</td>
                                    <td class="py-2 pr-0 text-right">$ {{ number_format((float)($d->iva ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-gray-500">No hay conceptos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Totales (hasta abajo) --}}
            <div class="p-4 rounded-xl border bg-white max-w-md ml-auto">
                <div class="text-sm font-semibold text-gray-700 mb-3">Totales</div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-medium">$ {{ number_format($tSubtotal, 2) }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Descuento</span>
                        <span class="font-medium">$ {{ number_format($tDescuento, 2) }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">IVA</span>
                        <span class="font-medium">$ {{ number_format($tIva, 2) }}</span>
                    </div>

                    <div class="border-t pt-2 flex justify-between">
                        <span class="font-semibold">Total</span>
                        <span class="font-semibold">$ {{ number_format($tTotal, 2) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
