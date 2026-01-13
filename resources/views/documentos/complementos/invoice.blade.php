@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Complemento #{{ $comp->id }}</h1>
        <a href="{{ route('complementos.index') }}" class="btn bg-gray-100 dark:bg-gray-700">← Volver</a>
    </div>

    @if(session('success'))
        <div class="mb-3 p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
        <div class="text-sm text-gray-600 dark:text-gray-300">
            <div><b>UUID:</b> {{ $comp->uuid }}</div>
            <div><b>Estatus:</b> {{ strtoupper($comp->estatus) }}</div>
            <div><b>Receptor:</b> {{ $comp->razon_social }} ({{ $comp->rfc }})</div>
        </div>

        <div class="mt-4 flex gap-2">
            <a class="btn bg-gray-100 dark:bg-gray-700" target="_blank"
               href="data:application/xml;charset=utf-8,{{ urlencode($comp->xml) }}">
                Ver XML
            </a>

            @if(!empty($comp->pdf))
                <a class="btn btn-primary" target="_blank"
                   href="data:application/pdf;base64,{{ $comp->pdf }}">
                    Ver PDF
                </a>
            @endif
        </div>

        <h2 class="mt-6 font-semibold">Pagos</h2>
        <div class="overflow-x-auto mt-2">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="p-2 text-left">Factura ID</th>
                        <th class="p-2 text-left">Fecha pago</th>
                        <th class="p-2 text-right">Saldo ant</th>
                        <th class="p-2 text-right">Pago</th>
                        <th class="p-2 text-right">Saldo insoluto</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pagos as $p)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="p-2">{{ $p->documento_id }}</td>
                        <td class="p-2">{{ $p->fecha_pago }}</td>
                        <td class="p-2 text-right">{{ number_format((float)$p->saldo_anterior, 2) }}</td>
                        <td class="p-2 text-right">{{ number_format((float)$p->monto_pago, 2) }}</td>
                        <td class="p-2 text-right">{{ number_format((float)$p->saldo_insoluto, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
