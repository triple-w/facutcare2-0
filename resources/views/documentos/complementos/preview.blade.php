@extends('layouts.app')

@section('content')
<div class="p-6 max-w-7xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Previsualización Complemento de Pago</h1>
    <a href="{{ route('complementos.create') }}" class="btn bg-gray-100 dark:bg-gray-700">← Volver</a>
  </div>

  @if(session('error'))
    <div class="mb-3 p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
  @endif

  <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow space-y-6">

    {{-- Encabezado --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2">
        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $cliente->razon_social ?? '' }}</div>
        <div class="text-sm text-gray-500">{{ $cliente->rfc ?? '' }}</div>

        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
          Fecha documento: <b>{{ $payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? '') }}</b>
        </div>
        <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
          Fecha pago: <b>{{ $payload['fecha_pago'] ?? '' }}</b>
        </div>
      </div>

      <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="text-sm text-gray-600 dark:text-gray-300">Complemento</div>
        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
          {{ ($payload['serie_pago'] ?? '') }}{{ ($payload['folio_pago'] ?? 0) ? '-'.$payload['folio_pago'] : '' }}
        </div>
        <div class="mt-2 text-xs text-gray-500">
          FormaP: <span class="font-mono">{{ $payload['forma_pago_p'] ?? '' }}</span> ·
          MonedaP: <span class="font-mono">{{ $payload['moneda_p'] ?? '' }}</span> ·
          TC: <span class="font-mono">{{ number_format((float)($payload['tipo_cambio_p'] ?? 1), 6) }}</span>
        </div>
      </div>
    </div>

    {{-- Datos bancarios (opcionales) --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Datos bancarios (opcionales)</h2>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 text-sm">
        <div>
          <div class="text-gray-500">Número de operación</div>
          <div class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['num_operacion'] ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">RFC banco emisor</div>
          <div class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['rfc_banco_emisor'] ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">Cuenta ordenante</div>
          <div class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['cuenta_ordenante'] ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">Banco receptor</div>
          <div class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['banco_receptor'] ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">Cuenta beneficiaria</div>
          <div class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['cuenta_beneficiaria'] ?? '—' }}</div>
        </div>
      </div>
    </div>

    {{-- Tabla de doctos --}}
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Doctos relacionados</h2>
      </div>

      <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-700 dark:text-gray-200">
            <tr>
              <th class="p-2 text-left">Serie/Folio</th>
              <th class="p-2 text-left">UUID</th>
              <th class="p-2 text-center">Parc.</th>
              <th class="p-2 text-center">MonedaDR</th>
              <th class="p-2 text-center">MétodoDR</th>
              <th class="p-2 text-right">Saldo anterior</th>
              <th class="p-2 text-right">Pago</th>
              <th class="p-2 text-right">Saldo insoluto</th>
              <th class="p-2 text-left">Impuestos</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          @forelse(($payload['pagos'] ?? []) as $p)
            @php
              $imps = is_array($p['impuestos'] ?? null) ? $p['impuestos'] : [];
              $obj  = (bool)($p['objeto_imp'] ?? false);
            @endphp
            <tr class="bg-white dark:bg-gray-800">
              <td class="p-2">
                <div class="font-medium text-gray-900 dark:text-gray-100">
                  {{ $p['serie_folio'] ?? ('#'.($p['factura_id'] ?? '')) }}
                </div>
                <div class="text-xs text-gray-500">{{ $p['nombre'] ?? '' }}</div>
              </td>

              <td class="p-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $p['uuid'] ?? '' }}</td>

              <td class="p-2 text-center">{{ (int)($p['num_parcialidad'] ?? 1) }}</td>
              <td class="p-2 text-center">{{ $p['moneda_dr'] ?? 'MXN' }}</td>
              <td class="p-2 text-center">{{ $p['metodo_pago_dr'] ?? 'PPD' }}</td>

              <td class="p-2 text-right">{{ number_format((float)($p['saldo_anterior'] ?? 0), 2) }}</td>
              <td class="p-2 text-right font-semibold">{{ number_format((float)($p['monto_pago'] ?? 0), 2) }}</td>
              <td class="p-2 text-right">{{ number_format((float)($p['saldo_insoluto'] ?? 0), 2) }}</td>

              <td class="p-2">
                @if(!$obj)
                  <span class="text-gray-500">—</span>
                @else
                  @if(empty($imps))
                    <span class="text-gray-500">Sin impuestos</span>
                  @else
                    <div class="space-y-1">
                      @foreach($imps as $it)
                        @php
                          $tipo = strtoupper((string)($it['tipo'] ?? 'T')) === 'R' ? 'Ret' : 'Tras';
                          $imp  = (string)($it['impuesto_sat'] ?? $it['impuesto'] ?? 'IVA');
                          $fac  = (string)($it['factor'] ?? 'Tasa');
                          $tasa = (strtolower($fac)==='exento')
                            ? 'Exento'
                            : (string)($it['tasa_cuota'] ?? number_format(((float)($it['tasa'] ?? 0)) / 100, 6, '.', ''));
                          $base = number_format((float)($it['base'] ?? 0), 2);
                          $impv = number_format((float)($it['importe'] ?? 0), 2);
                        @endphp
                        <div class="text-xs text-gray-700 dark:text-gray-200">
                          <span class="inline-block px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                            {{ $tipo }}
                          </span>
                          <span class="ml-1 font-medium">{{ $imp }}</span>
                          <span class="ml-2 text-gray-500">TipoFactorDR:</span> <span class="font-mono">{{ $fac }}</span>
                          <span class="ml-2 text-gray-500">TasaOCuotaDR:</span> <span class="font-mono">{{ $tasa }}</span>
                          <span class="ml-2 text-gray-500">BaseDR:</span> <span class="font-mono">{{ $base }}</span>
                          <span class="ml-2 text-gray-500">ImporteDR:</span> <span class="font-mono">{{ $impv }}</span>
                        </div>
                      @endforeach
                    </div>
                  @endif
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="p-4 text-center text-gray-500">No hay facturas en el payload.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Totales --}}
    <div class="flex justify-end">
      <div class="w-full lg:w-1/3 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-500">Subtotal</span>
          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float)($totales['subtotal'] ?? 0), 2) }}</span>
        </div>

        <div class="flex justify-between mt-1">
          <span class="text-gray-500">Impuestos trasladados</span>
          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float)($totales['traslados'] ?? 0), 2) }}</span>
        </div>

        <div class="flex justify-between mt-1">
          <span class="text-gray-500">Impuestos retenidos</span>
          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float)($totales['retenciones'] ?? 0), 2) }}</span>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 my-3"></div>

        <div class="flex justify-between text-base font-semibold">
          <span>Total</span>
          <span class="text-gray-900 dark:text-gray-100">{{ number_format((float)($totales['total'] ?? 0), 2) }}</span>
        </div>
      </div>
    </div>

    {{-- Acciones --}}
    <div class="flex gap-2">
      @if(\Route::has('complementos.timbrar'))
        <form method="POST" action="{{ route('complementos.timbrar') }}">
          @csrf
          <input type="hidden" name="payload" value='@json($payload)'>

          <button type="submit" name="modo" value="debug" formtarget="_blank"
                  class="btn bg-gray-100 dark:bg-gray-700">
            Ver XML (debug)
          </button>

          <button type="submit" name="modo" value="timbrar" class="btn btn-primary">
            Timbrar
          </button>
        </form>
      @else
        <div class="text-sm text-gray-500">
          La ruta <span class="font-mono">complementos.timbrar</span> no está configurada aún.
        </div>
      @endif
    </div>

  </div>
</div>
@endsection
