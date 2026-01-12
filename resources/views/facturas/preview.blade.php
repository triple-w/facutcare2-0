<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Previsualización</h2>
            <div class="text-sm text-gray-500">
                RFC emisor: <span class="font-medium">{{ $emisor_rfc }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if(session('ok'))
                <div class="mb-4 p-3 rounded bg-green-50 text-green-700 text-sm">{{ session('ok') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white rounded-xl shadow p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                        <div class="text-gray-500">Tipo</div>
                        @php
                        $map = ['I'=>'INGRESO','E'=>'EGRESO','T'=>'TRASLADO'];
                        @endphp
                        <div class="font-semibold">{{ $map[$comprobante['tipo_comprobante']] ?? $comprobante['tipo_comprobante'] }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Serie/Folio</div>
                        <div class="font-semibold">{{ $comprobante['serie'] }}-{{ $comprobante['folio'] }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Fecha</div>
                        <div class="font-semibold">{{ \Illuminate\Support\Carbon::parse($comprobante['fecha'])->format('Y-m-d H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Método de pago</div>
                        <div class="font-semibold">{{ $comprobante['metodo_pago'] }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Forma de pago</div>
                        <div class="font-semibold">{{ $comprobante['forma_pago'] }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Cliente</div>
                        <div class="font-semibold">{{ $cliente->razon_social }} — {{ $cliente->rfc }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-4 mb-6 overflow-x-auto">
                <table class="table-auto w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="px-2 py-2">Clave</th>
                            <th class="px-2 py-2">Descripción</th>
                            <th class="px-2 py-2 text-right">Cant.</th>
                            <th class="px-2 py-2 text-right">V. Unit.</th>
                            <th class="px-2 py-2 text-right">Desc.</th>
                            <th class="px-2 py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($comprobante['conceptos'] as $c)
                            @php
                                $sub = (float)$c['cantidad'] * (float)$c['precio'];
                                $des = (float)($c['descuento'] ?? 0);
                                $importe = max($sub - $des, 0);
                            @endphp
                            <tr class="border-b border-gray-100">
                                <td class="px-2 py-2 align-top">{{ $c['clave_prod_serv'] }}/{{ $c['clave_unidad'] }}</td>
                                <td class="px-2 py-2 align-top">
                                    <div class="font-medium">{{ $c['descripcion'] }}</div>
                                    <div class="text-xs text-gray-500">Unidad: {{ $c['unidad'] }}</div>
                                </td>
                                <td class="px-2 py-2 text-right align-top">{{ number_format($c['cantidad'],3) }}</td>
                                <td class="px-2 py-2 text-right align-top">{{ number_format($c['precio'],2) }}</td>
                                <td class="px-2 py-2 text-right align-top">{{ number_format($des,2) }}</td>
                                <td class="px-2 py-2 text-right align-top">{{ number_format($importe,2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(!empty($comprobante['comentarios_pdf']))
                <div class="bg-white rounded-xl shadow p-4 mb-6">
                    <h3 class="text-sm font-semibold mb-2">Comentarios (PDF)</h3>
                    <div class="text-sm whitespace-pre-line">{{ $comprobante['comentarios_pdf'] }}</div>
                </div>
            @endif

            <div class="flex justify-end">
                <div class="w-full max-w-sm space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>{{ number_format($totales['subtotal'],2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Descuento</span><span>{{ number_format($totales['descuento'],2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Impuestos</span><span>{{ number_format($totales['impuestos'],2) }}</span></div>
                    <div class="flex justify-between font-semibold text-gray-700"><span>Total</span><span>{{ number_format($totales['total'],2) }}</span></div>
                </div>
            </div>

            <div class="mt-6 flex gap-2">
                <button type="button" class="px-4 py-2 bg-gray-100 rounded-md"
                        onclick="document.getElementById('formGuardarBorrador').submit()">
                    Guardar borrador
                </button>

                <button type="button" class="px-4 py-2 bg-violet-600 text-white rounded-md"
                        onclick="document.getElementById('formTimbrar').submit()">
                    Timbrar
                </button>

                <a href="{{ route('facturas.create') }}" class="px-4 py-2 bg-gray-100 rounded-md">
                    ← Regresar
                </a>
            </div>

            <form id="formGuardarBorrador" method="POST" action="{{ route('facturas.guardar') }}" class="hidden">
                @csrf
                <input type="hidden" name="payload" value='@json($comprobante, JSON_UNESCAPED_UNICODE)'>
            </form>

            <form id="formTimbrar" method="POST" action="{{ route('facturas.timbrar') }}" class="hidden">
                @csrf
                <input type="hidden" name="payload" value='@json($comprobante, JSON_UNESCAPED_UNICODE)'>
            </form>

        </div>
    </div>
</x-app-layout>
