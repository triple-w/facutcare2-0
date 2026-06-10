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
            <a class="btn bg-gray-100 dark:bg-gray-700"
               href="{{ route('complementos.xml', $comp->id) }}">
                Descargar XML
            </a>

            @if(!empty($comp->pdf))
                <a class="btn btn-primary"
                   href="{{ route('complementos.pdf', $comp->id) }}">
                    Descargar PDF
                </a>
            @endif

            <form method="POST" action="{{ route('complementos.regenerarPdf', $comp->id) }}">
                @csrf
                <button type="submit"
                        class="btn bg-yellow-500 text-black hover:bg-yellow-600"
                        onclick="return confirm('¿Seguro de regenerar el PDF?');">
                    Regenerar PDF
                </button>
            </form>
        </div>

        @php($p20 = is_array($pagos20 ?? null) ? $pagos20 : ['totales' => [], 'pagos' => []])

        <h2 class="mt-6 font-semibold">Totales Pagos 2.0</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2 text-sm">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="text-gray-500">MontoTotalPagos</div>
                <div class="font-semibold">{{ $p20['totales']['MontoTotalPagos'] ?? '0.00' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="text-gray-500">TotalTrasladosBaseIVA16</div>
                <div class="font-semibold">{{ $p20['totales']['TotalTrasladosBaseIVA16'] ?? '0.00' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="text-gray-500">TotalTrasladosImpuestoIVA16</div>
                <div class="font-semibold">{{ $p20['totales']['TotalTrasladosImpuestoIVA16'] ?? '0.00' }}</div>
            </div>
        </div>

        @if(!empty($p20['pagos']))
            <h2 class="mt-6 font-semibold">Impuestos del XML</h2>
            <div class="overflow-x-auto mt-2">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="p-2 text-left">Documento</th>
                            <th class="p-2 text-right">ImpPagado</th>
                            <th class="p-2 text-center">ObjetoImpDR</th>
                            <th class="p-2 text-right">BaseDR</th>
                            <th class="p-2 text-center">Tasa</th>
                            <th class="p-2 text-right">ImporteDR</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($p20['pagos'] as $pagoXml)
                        @foreach(($pagoXml['doctos'] ?? []) as $doc)
                            @forelse(($doc['traslados_dr'] ?? []) as $tdr)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="p-2 font-mono text-xs">{{ $doc['IdDocumento'] ?? '' }}</td>
                                    <td class="p-2 text-right">{{ $doc['ImpPagado'] ?? '' }}</td>
                                    <td class="p-2 text-center">{{ $doc['ObjetoImpDR'] ?? '' }}</td>
                                    <td class="p-2 text-right">{{ $tdr['BaseDR'] ?? '' }}</td>
                                    <td class="p-2 text-center">{{ $tdr['TasaOCuotaDR'] ?? '' }}</td>
                                    <td class="p-2 text-right">{{ $tdr['ImporteDR'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="p-2 font-mono text-xs">{{ $doc['IdDocumento'] ?? '' }}</td>
                                    <td class="p-2 text-right">{{ $doc['ImpPagado'] ?? '' }}</td>
                                    <td class="p-2 text-center">{{ $doc['ObjetoImpDR'] ?? '' }}</td>
                                    <td class="p-2 text-center text-gray-500" colspan="3">Sin TrasladoDR</td>
                                </tr>
                            @endforelse
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

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
