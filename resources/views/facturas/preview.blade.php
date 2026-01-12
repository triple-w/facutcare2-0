@extends('layouts.app')

@section('title', 'Previsualización de factura')

@section('content')
<div class="px-6 py-6 w-full max-w-7xl mx-auto">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Previsualización de factura</h1>
    <a href="{{ route('facturas.create') }}"
       class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
      ← Volver a editar
    </a>
    <button type="button"
          class="btn border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200"
          onclick="alert('Guardar borrador: pendiente');">
    Guardar borrador
  </button>

  <form method="POST" action="{{ route('facturas.timbrar') }}">
    @csrf
    <button type="submit"
            class="btn bg-emerald-600 hover:bg-emerald-700 text-white">
      Timbrar
    </button>
  </div>
  
  {{-- Datos generales --}}
  <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Datos del comprobante</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-sm">
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
      <div class="col-span-4">
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
            <th class="p-2 text-right">IVA</th>
            <th class="p-2 text-right">Importe</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @foreach($conceptos as $c)
          @php
            $cantidad = (float)($c['cantidad'] ?? 0);
            $precio   = (float)($c['precio'] ?? 0);
            $descuento = (float)($c['descuento'] ?? 0);
            $ivaTasa   = (float)($c['iva_tasa'] ?? 0);
            $importe   = ($cantidad * $precio) - $descuento;
            $ivaMonto  = ($c['aplica_iva'] ?? false) ? ($importe * $ivaTasa) : 0;
          @endphp
          <tr>
            <td class="p-2">{{ $cantidad }}</td>
            <td class="p-2">{{ $c['descripcion'] ?? '' }}</td>
            <td class="p-2">{{ $c['clave_prod_serv'] ?? '' }}</td>
            <td class="p-2">{{ $c['clave_unidad'] ?? '' }}</td>
            <td class="p-2">{{ $c['unidad'] ?? '' }}</td>
            <td class="p-2 text-right">${{ number_format($precio, 2) }}</td>
            <td class="p-2 text-right">${{ number_format($descuento, 2) }}</td>
            <td class="p-2 text-right">
              {{ ($c['aplica_iva'] ?? false) ? number_format($ivaTasa * 100, 2).'%' : '0%' }}
            </td>
            <td class="p-2 text-right">${{ number_format($importe + $ivaMonto, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Totales --}}
  @php
    $subtotal = collect($conceptos)->sum(fn($r) => ($r['cantidad']??0)*($r['precio']??0));
    $descTotal = collect($conceptos)->sum('descuento');
    $base = $subtotal - $descTotal;
    $ivaTotal = collect($conceptos)->sum(function($r){
      $imp = (($r['cantidad']??0)*($r['precio']??0)) - ($r['descuento']??0);
      return ($r['aplica_iva']??false) ? ($imp * ($r['iva_tasa']??0)) : 0;
    });
    $total = $base + $ivaTotal;
  @endphp

  <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-sm">
    <div class="flex flex-col md:flex-row md:justify-end gap-2">
      <div class="md:w-1/3 space-y-1">
        <div class="flex justify-between"><span>Subtotal</span><span>${{ number_format($subtotal,2) }}</span></div>
        <div class="flex justify-between"><span>Descuento</span><span>${{ number_format($descTotal,2) }}</span></div>
        <div class="flex justify-between"><span>IVA</span><span>${{ number_format($ivaTotal,2) }}</span></div>
        <div class="border-t border-gray-300 my-1"></div>
        <div class="flex justify-between font-semibold text-base">
          <span>Total</span><span>${{ number_format($total,2) }}</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
