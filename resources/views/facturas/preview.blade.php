<x-app-layout>
    <x-slot name="header">
        <div class="text-xl font-semibold">Vista previa</div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm font-semibold text-gray-700">Comprobante</div>
                <div class="text-sm text-gray-600 mt-2 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><b>RFC activo:</b> {{ $comprobante['rfc_activo'] ?: '—' }}</div>
                    <div><b>Tipo:</b> {{ $comprobante['tipo_comprobante'] }}</div>
                    <div><b>Moneda:</b> {{ $comprobante['moneda'] }}</div>
                    <div><b>Método:</b> {{ $comprobante['metodo_pago'] }}</div>
                    <div><b>Forma:</b> {{ $comprobante['forma_pago'] }}</div>
                </div>
            </div>

            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm font-semibold text-gray-700">Receptor</div>
                <div class="text-sm text-gray-600 mt-2">
                    <div><b>{{ $cliente->razon_social }}</b></div>
                    <div>RFC: <span class="font-mono">{{ $cliente->rfc }}</span></div>
                </div>
            </div>

            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm font-semibold text-gray-700 mb-3">Conceptos</div>

                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr class="border-b">
                                <th class="py-2 pr-3">Cant</th>
                                <th class="py-2 pr-3">Descripción</th>
                                <th class="py-2 pr-3 text-right">Importe</th>
                                <th class="py-2 pr-0 text-right">IVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($conceptosLimpios as $c)
                                <tr>
                                    <td class="py-2 pr-3">{{ $c['cantidad'] }}</td>
                                    <td class="py-2 pr-3">
                                        <div class="font-medium">{{ $c['descripcion'] }}</div>
                                        <div class="text-xs text-gray-500">
                                            Prod/Serv: {{ $c['clave_prod_serv'] ?: '—' }} | Unidad: {{ $c['clave_unidad'] ?: '—' }}
                                        </div>
                                    </td>
                                    <td class="py-2 pr-3 text-right">$ {{ number_format($c['importe'], 2) }}</td>
                                    <td class="py-2 pr-0 text-right">$ {{ number_format($c['iva'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 max-w-md ml-auto space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-medium">$ {{ number_format($totales['subtotal'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">IVA</span>
                        <span class="font-medium">$ {{ number_format($totales['iva'], 2) }}</span>
                    </div>
                    <div class="border-t pt-2 flex justify-between">
                        <span class="font-semibold">Total</span>
                        <span class="font-semibold">$ {{ number_format($totales['total'], 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <a href="{{ route('facturas.create') }}" class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50">
                    Volver a editar
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
