@extends('layouts.app')

@section('title', 'Previsualización de factura')

@section('content')
<div class="px-6 py-6 w-full max-w-7xl mx-auto">
    @if(session('error'))
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
        {{ session('error') }}
    </div>
    @endif

    @if(session('success'))
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
        {{ session('success') }}
    </div>
    @endif

  @php
    $accionesPreview = function () {
      ob_start();
  @endphp
        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 items-stretch sm:items-center w-full sm:w-auto">
            <a href="{{ route('facturas.create') }}"
            class="btn w-full sm:w-auto text-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
                ← Volver a editar
            </a>

            <button type="button"
                    class="btn w-full sm:w-auto border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200"
                    onclick="alert('Guardar borrador: pendiente');">
                Guardar borrador
            </button>

            {{-- DEBUG XML: form propio para abrir en nueva pestaña --}}
            <form method="POST" action="{{ route('facturas.timbrar') }}" target="_blank" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="modo" value="debug">
                <button type="submit"
                        class="btn w-full sm:w-auto border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200">
                    Ver XML (debug)
                </button>
            </form>

            {{-- TIMBRAR: form propio (redirect normal) --}}
            <form method="POST" action="{{ route('facturas.timbrar') }}" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="modo" value="timbrar">
                <button type="submit" class="btn btn-primary w-full sm:w-auto">
                    Timbrar
                </button>
            </form>
        </div>
  @php
      return ob_get_clean();
    };
  @endphp

  <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Previsualización de factura</h1>
        {!! $accionesPreview() !!}

    </div>

  
  {{-- Datos generales --}}
  <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Datos del comprobante</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-sm">
      <div><span class="text-gray-500">RFC activo:</span><br>{{ $comprobante['rfc_activo'] ?? '—' }}</div>
      <div><span class="text-gray-500">Tipo:</span><br>{{ $comprobante['tipo_comprobante'] ?? '—' }}</div>
      <div><span class="text-gray-500">Serie:</span><br>{{ $comprobante['serie'] ?? '—' }}</div>
      <div><span class="text-gray-500">Folio:</span><br>{{ $comprobante['folio'] ?? '—' }}</div>
      <div><span class="text-gray-500">Fecha:</span><br>{{ $comprobante['fecha'] ?? '—' }}</div>
      <div><span class="text-gray-500">Método de pago:</span><br>{{ $comprobante['metodo_pago'] ?? '—' }}</div>
      <div><span class="text-gray-500">Forma de pago:</span><br>{{ $comprobante['forma_pago'] ?? '—' }}</div>
      <div><span class="text-gray-500">Uso CFDI:</span><br>{{ $comprobante['uso_cfdi'] ?? '—' }}</div>
      <div><span class="text-gray-500">Exportación:</span><br>{{ $comprobante['exportacion'] ?? '—' }}</div>
      <div><span class="text-gray-500">Moneda:</span><br>{{ $comprobante['moneda'] ?? '—' }}</div>
      <div><span class="text-gray-500">Descuento:</span><br>${{ number_format($comprobante['descuento'] ?? 0, 2) }}</div>
      <div class="sm:col-span-2 md:col-span-3 lg:col-span-4">
        <span class="text-gray-500">Comentarios PDF:</span><br>
        <span class="whitespace-pre-line">{{ $comprobante['comentarios_pdf'] ?? '—' }}</span>
      </div>
    </div>
  </div>

  {{-- Conceptos --}}
  <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Conceptos</h2>
    <div class="overflow-x-auto">
      <table class="table-auto w-full text-sm border-collapse border border-gray-200 dark:border-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs">
          <tr>
            <th class="p-2 text-left">Cantidad</th>
            <th class="p-2 text-left">Descripción</th>
            <th class="p-2 text-left">Clave ProdServ</th>
            <th class="p-2 text-left">Clave Unidad</th>
            <th class="p-2 text-left">Unidad</th>
            <th class="p-2 text-right">Precio</th>
            <th class="p-2 text-right">Descuento</th>
            <th class="p-2 text-left">Impuestos</th>
            <th class="p-2 text-right">Total línea</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @foreach($conceptos as $c)
          <tr>
            <td class="p-2">{{ number_format((float)($c['cantidad'] ?? 0), 2) }}</td>
            <td class="p-2">{{ $c['descripcion'] ?? '' }}</td>
            <td class="p-2">{{ $c['clave_prod_serv'] ?? '' }}</td>
            <td class="p-2">{{ $c['clave_unidad'] ?? '' }}</td>
            <td class="p-2">{{ $c['unidad'] ?? '' }}</td>
            <td class="p-2 text-right">${{ number_format((float)($c['precio'] ?? 0), 2) }}</td>
            <td class="p-2 text-right">${{ number_format((float)($c['descuento'] ?? 0), 2) }}</td>
            <td class="p-2">
              <div class="space-y-1">
                @forelse(($c['impuestos'] ?? []) as $imp)
                  <div class="flex justify-between gap-3 text-xs">
                    <span class="text-gray-600 dark:text-gray-300">{{ $imp['descripcion'] ?? '—' }}</span>
                    <span class="{{ ($imp['tipo'] ?? 'T') === 'R' ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                      {{ ($imp['tipo'] ?? 'T') === 'R' ? '-' : '+' }}${{ number_format((float)($imp['importe'] ?? 0), 2) }}
                    </span>
                  </div>
                @empty
                  <span class="text-xs text-gray-500">Sin impuestos</span>
                @endforelse
              </div>
            </td>
            <td class="p-2 text-right">${{ number_format((float)($c['importe_neto'] ?? 0), 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-sm">
    <div class="flex flex-col md:flex-row md:justify-end gap-2">
      <div class="md:w-1/3 space-y-1">
        <div class="flex justify-between"><span>Subtotal</span><span>${{ number_format((float)($totales['subtotal'] ?? 0),2) }}</span></div>
        <div class="flex justify-between"><span>Descuento</span><span>${{ number_format((float)($totales['descuento'] ?? 0),2) }}</span></div>
        <div class="flex justify-between"><span>Base gravable</span><span>${{ number_format((float)($totales['base'] ?? 0),2) }}</span></div>
        <div class="flex justify-between"><span>Traslados</span><span>${{ number_format((float)($totales['traslados'] ?? 0),2) }}</span></div>
        <div class="flex justify-between"><span>Retenciones</span><span>-${{ number_format((float)($totales['retenciones'] ?? 0),2) }}</span></div>
        @if((float)($totales['ret_local_total'] ?? 0) > 0)
        <div class="flex justify-between"><span>Retenciones locales</span><span>-${{ number_format((float)($totales['ret_local_total'] ?? 0),2) }}</span></div>
        @endif
        <div class="border-t border-gray-300 my-1"></div>
        <div class="flex justify-between font-semibold text-base">
          <span>Total</span><span>${{ number_format((float)($totales['total'] ?? 0),2) }}</span>
        </div>
      </div>
    </div>
  </div>
  <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mt-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Acciones de timbrado</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Dejé los mismos botones aquí abajo para no obligarte a volver al encabezado en móvil o en facturas largas.</p>
      </div>
      {!! $accionesPreview() !!}
    </div>
  </div>
 <pre class="text-xs overflow-auto max-h-96 bg-gray-50 p-3 rounded">
 {{ json_encode(session('factura_draft'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
</pre>
</div>
@endsection
