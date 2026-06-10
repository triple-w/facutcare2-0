@extends('layouts.app')

@section('title','Crear complemento de pago')

@php
  // Fallback por si el controlador aún no manda $opts
  $opts = $opts ?? [
    'csrf' => csrf_token(),
    'clientes' => $clientes ?? [],
    'prefill' => $draft ?? null,
    'foliosPago' => $foliosPago ?? [],
    'endpoints' => [
      'preview' => route('complementos.preview'),
      'facturasPendientes' => route('complementos.facturasPendientes'),
    ],
  ];
@endphp

@section('content')
<div class="px-6 py-6 w-full max-w-7xl mx-auto"
     x-data="window.complementoCreate(@js($opts))"
     x-init="init()">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Crear complemento de pago</h1>

    <div class="flex items-center gap-2">
      <a href="{{ route('complementos.index') }}"
         class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
        ← Volver
      </a>

      <button type="button"
              class="btn border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200"
              @click="guardarBorrador()">
        Guardar borrador
      </button>

      <button type="button"
              class="btn btn-primary"
              :disabled="isSubmitting"
              @click="previsualizar()">
        <span x-show="!isSubmitting">Previsualizar</span>
        <span x-show="isSubmitting">Procesando…</span>
      </button>
    </div>
  </div>

  {{-- FORM Preview (payload JSON) --}}
  <form x-ref="previewForm" method="POST" action="{{ route('complementos.preview') }}" class="hidden">
    @csrf
    <input type="hidden" name="payload" x-ref="payload">
  </form>

  {{-- =========================
       1) Cliente + Fecha + Serie/Folio complemento
     ========================= --}}
  <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Cliente, fecha y folio</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      {{-- Cliente (combo buscable) --}}
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente</label>

        <div class="relative" @click.outside="ui.cliente.open=false">
          <input type="text"
                 class="form-input w-full"
                 placeholder="Escribe razón social o RFC…"
                 x-model="ui.cliente.q"
                 @focus="ui.cliente.open=true"
                 @input.debounce.150ms="filtrarClientes()">

          <button type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  @click="ui.cliente.open = !ui.cliente.open"
                  title="Mostrar lista">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div x-show="ui.cliente.open"
               x-transition
               class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow max-h-72 overflow-auto">
            <template x-for="c in ui.cliente.items" :key="c.id">
              <button type="button"
                      class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800"
                      @click="selectCliente(c)">
                <div class="text-sm font-medium text-gray-800 dark:text-gray-100" x-text="c.razon_social || '—'"></div>
                <div class="text-xs text-gray-500" x-text="c.rfc || ''"></div>
              </button>
            </template>

            <div x-show="ui.cliente.items.length===0" class="px-3 py-2 text-sm text-gray-500">
              Sin resultados.
            </div>
          </div>
        </div>

        <p class="mt-2 text-xs text-gray-500" x-show="form.cliente_id">
          Cliente seleccionado: <span class="font-semibold" x-text="clienteSel?.razon_social || ''"></span>
          <span class="font-mono" x-text="clienteSel?.rfc ? '— ' + clienteSel.rfc : ''"></span>
        </p>
      </div>

      {{-- Fecha documento --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha del documento</label>
        <input type="datetime-local"
               class="form-input w-full"
               x-model="form.fecha_documento"
               @change="recalcular()">
        <p class="mt-2 text-xs text-gray-500">Se usa en el atributo Fecha del CFDI (Comprobante).</p>
      </div>

      {{-- Fecha pago --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de pago</label>
        <input type="datetime-local"
               class="form-input w-full"
               x-model="form.fecha_pago"
               @change="recalcular()">
        <p class="mt-2 text-xs text-gray-500">Se usa en el nodo Pago@FechaPago.</p>
      </div>

      {{-- Serie/Folio Complemento --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Serie (Complemento)</label>
        <select class="form-select w-full" x-model="form.serie_pago" @change="onSeriePagoChange()">
          <template x-for="s in pool.foliosPago" :key="s.id">
            <option :value="s.serie" x-text="s.serie"></option>
          </template>
        </select>
        <p class="mt-1 text-xs text-gray-500" x-show="pool.foliosPago.length===0">No hay folios tipo PAGO.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Folio (Complemento)</label>
        <input type="number" min="1" class="form-input w-full" x-model.number="form.folio_pago">
        <p class="mt-1 text-xs text-gray-500" x-show="form.serie_pago">
          Sugerido por folios PAGO.
        </p>
      </div>

      <div class="hidden lg:block"></div>
    </div>
  </div>

  {{-- =========================
       2) Facturas (como productos)
     ========================= --}}
  <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Facturas a relacionar</h2>

      <div class="flex items-center gap-2">
        <button type="button"
                class="btn border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200"
                @click="recargarPendientes()"
                :disabled="!form.cliente_id">
          Recargar
        </button>
      </div>
    </div>

    {{-- Buscador --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar factura pendiente</label>

        <div class="relative" @click.outside="ui.fact.open=false">
          <input type="text"
                 class="form-input w-full"
                 placeholder="UUID, Serie/Folio, Razón social…"
                 x-model="ui.fact.q"
                 :disabled="!form.cliente_id"
                 @focus="ui.fact.open=true"
                 @input.debounce.150ms="filtrarPendientes()">

          <div x-show="ui.fact.open"
               x-transition
               class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow max-h-72 overflow-auto">
            <template x-for="f in ui.fact.items" :key="f.id">
              <button type="button"
                      class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800"
                      @click="agregarFactura(f)">
                <div class="flex items-center justify-between gap-3">
                  <div class="text-sm font-medium text-gray-800 dark:text-gray-100">
                    <span x-text="(f.serie && f.folio) ? (f.serie+'-'+f.folio) : ('#'+f.id)"></span>
                    <span class="ml-2 text-xs text-gray-500 font-mono" x-text="f.uuid ? f.uuid : ''"></span>
                  </div>
                  <div class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="money(f.saldo_insoluto ?? 0)"></div>
                </div>
                <div class="text-xs text-gray-500">
                  Saldo insoluto · <span x-text="money(f.saldo_insoluto ?? 0)"></span>
                  <span class="mx-1">•</span>
                  Método DR: <span x-text="f.metodo_pago_dr || 'PPD'"></span>
                  <span class="mx-1">•</span>
                  Moneda DR: <span x-text="f.moneda_dr || 'MXN'"></span>
                </div>
              </button>
            </template>

            <div x-show="ui.fact.items.length===0" class="px-3 py-2 text-sm text-gray-500">
              <span x-show="!form.cliente_id">Selecciona un cliente para cargar pendientes.</span>
              <span x-show="form.cliente_id">Sin resultados.</span>
            </div>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pool cargado</label>
        <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200">
          <div><span class="text-gray-500">Pendientes:</span> <span class="font-semibold" x-text="pool.pendientes.length"></span></div>
          <div class="mt-1"><span class="text-gray-500">Agregadas:</span> <span class="font-semibold" x-text="form.pagos.length"></span></div>
        </div>
      </div>
    </div>

    {{-- Tabla doctos --}}
    <div class="overflow-x-auto">
      <table class="w-full border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
          <tr>
            <th class="px-3 py-2 text-left">Serie/Folio</th>
            <th class="px-3 py-2 text-left">UUID</th>
            <th class="px-3 py-2 text-right">Saldo anterior</th>
            <th class="px-3 py-2 text-right">Monto pago</th>
            <th class="px-3 py-2 text-right">Saldo insoluto</th>
            <th class="px-3 py-2 text-center">Parcialidad</th>
            <th class="px-3 py-2 text-center">MonedaDR</th>
            <th class="px-3 py-2 text-center">MétodoDR</th>
            <th class="px-3 py-2 text-center">ObjetoImp</th>
            <th class="px-3 py-2 text-left">Impuestos</th>
            <th class="px-3 py-2 text-right">Acciones</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <template x-for="(p, idx) in form.pagos" :key="p.uid">
            <tr>
              <td class="px-3 py-2 font-medium">
                <div x-text="p.serie_folio || ('#'+(p.factura_id||''))"></div>
                <div class="text-xs text-gray-500" x-text="p.nombre || ''"></div>
              </td>

              <td class="px-3 py-2 font-mono text-xs">
                <span x-text="p.uuid"></span>
              </td>

              <td class="px-3 py-2 text-right">
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-input w-32 text-right"
                       x-model.number="p.saldo_anterior"
                       @input.debounce.150ms="recalcular()">
              </td>

              <td class="px-3 py-2 text-right">
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-input w-32 text-right"
                       x-model.number="p.monto_pago"
                       @input.debounce.150ms="recalcular()">
              </td>

              <td class="px-3 py-2 text-right">
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-input w-32 text-right"
                       x-model.number="p.saldo_insoluto"
                       @input.debounce.150ms="recalcular()">
              </td>

              <td class="px-3 py-2 text-center">
                <input type="number"
                       min="1"
                       class="form-input w-20 text-center"
                       x-model.number="p.num_parcialidad"
                       @input.debounce.150ms="recalcular()">
              </td>

              <td class="px-3 py-2 text-center" x-text="p.moneda_dr"></td>
              <td class="px-3 py-2 text-center" x-text="p.metodo_pago_dr"></td>

              <td class="px-3 py-2 text-center">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" class="form-checkbox"
                         x-model="p.objeto_imp"
                         @change="onToggleObjetoImp(idx)">
                  <span class="text-xs text-gray-600 dark:text-gray-300">Sí</span>
                </label>
              </td>

              <td class="px-3 py-2">
                <div class="text-xs text-gray-600 dark:text-gray-300" x-text="resumenImpuestos(p)"></div>
                <button type="button"
                        class="mt-1 text-xs text-blue-600 hover:text-blue-800"
                        @click="abrirImpuestos(idx)"
                        :disabled="!p.objeto_imp">
                  Editar impuestos
                </button>
              </td>

              <td class="px-3 py-2">
                <div class="flex justify-end">
                  <button type="button"
                          class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-600 text-white hover:bg-red-700"
                          title="Quitar"
                          @click="quitarFactura(idx)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </template>

          <tr x-show="form.pagos.length===0">
            <td colspan="11" class="px-3 py-6 text-center text-gray-500">
              Agrega facturas pendientes usando el buscador.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- =========================
       3) Datos bancarios (opcionales)
     ========================= --}}
  <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos bancarios (opcionales)</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RFC banco emisor</label>
        <input type="text" class="form-input w-full" x-model="form.rfc_banco_emisor">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cuenta ordenante</label>
        <input type="text" class="form-input w-full" x-model="form.cuenta_ordenante">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Banco receptor</label>
        <input type="text" class="form-input w-full" x-model="form.banco_receptor">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cuenta beneficiaria</label>
        <input type="text" class="form-input w-full" x-model="form.cuenta_beneficiaria">
      </div>

      <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de operación</label>
        <input type="text" class="form-input w-full" x-model="form.num_operacion">
      </div>
    </div>
  </div>

  {{-- =========================
       4) Datos del pago + Totales
     ========================= --}}
  <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos del pago</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de pago (P)</label>
        <input type="text" class="form-input w-full" x-model="form.forma_pago_p" @input.debounce.150ms="recalcular()">
        <p class="mt-1 text-xs text-gray-500">Ej: 03 Transferencia, 01 Efectivo, 99 Por definir…</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Moneda (P)</label>
        <input type="text" class="form-input w-full" x-model="form.moneda_p" @input.debounce.150ms="recalcular()">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo cambio (P)</label>
        <input type="number" step="0.000001" min="0" class="form-input w-full"
               x-model.number="form.tipo_cambio_p" @input.debounce.150ms="recalcular()">
        <p class="mt-1 text-xs text-gray-500">Solo requerido si MonedaP ≠ MXN.</p>
      </div>

      <div class="lg:col-span-2"></div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total (auto)</label>
        <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-100 font-semibold"
             x-text="money(totales.total)">
        </div>
      </div>
    </div>

    {{-- Totales --}}
    <div class="flex flex-col md:flex-row md:justify-end gap-2">
      <div class="md:w-1/3 space-y-1 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-600 dark:text-gray-300">Subtotal</span>
          <span class="font-semibold" x-text="money(totales.subtotal)"></span>
        </div>

        <div class="flex justify-between" x-show="totales.traslados > 0">
          <span class="text-gray-600 dark:text-gray-300">Impuestos trasladados</span>
          <span class="font-semibold" x-text="money(totales.traslados)"></span>
        </div>

        <div class="flex justify-between" x-show="totales.retenciones > 0">
          <span class="text-gray-600 dark:text-gray-300">Impuestos retenidos</span>
          <span class="font-semibold" x-text="money(totales.retenciones)"></span>
        </div>

        <div class="border-t border-gray-300/70 dark:border-gray-700 my-2"></div>

        <div class="flex justify-between font-semibold text-base">
          <span>Total</span>
          <span x-text="money(totales.total)"></span>
        </div>

        <p class="mt-2 text-xs text-gray-500">
          Nota: mostramos sumatoria de impuestos capturados por factura (para armar tu complemento).
        </p>
      </div>
    </div>
  </div>

  {{-- =========================
       Modal impuestos
     ========================= --}}
  <div x-show="modalImpuestos.open" x-transition class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/40" @click="cerrarImpuestos()"></div>

    <div class="relative bg-white dark:bg-gray-800 w-full max-w-3xl mx-4 rounded-xl shadow-xl p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Impuestos de la factura</h3>
        <button type="button" class="text-gray-500 hover:text-gray-800" @click="cerrarImpuestos()">✕</button>
      </div>

      <div class="text-xs text-gray-500 mb-3">
        Se capturan traslados/retenciones para esta factura (DoctoRelacionado). Ajusta según aplique.
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
          <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
            <tr>
              <th class="px-3 py-2 text-left">Tipo</th>
              <th class="px-3 py-2 text-left">Impuesto</th>
              <th class="px-3 py-2 text-left">Factor</th>
              <th class="px-3 py-2 text-right">Tasa %</th>
              <th class="px-3 py-2 text-right">Base</th>
              <th class="px-3 py-2 text-right">Importe</th>
              <th class="px-3 py-2 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <template x-for="(it, i) in impuestosEdit" :key="it.uid">
              <tr>
                <td class="px-3 py-2">
                  <select class="form-select" x-model="it.tipo" @change="recalcModal()">
                    <option value="T">Traslado</option>
                    <option value="R">Retención</option>
                  </select>
                </td>

                <td class="px-3 py-2">
                  <select class="form-select" x-model="it.impuesto" @change="recalcModal()">
                    <option value="IVA">IVA (002)</option>
                    <option value="ISR">ISR (001)</option>
                    <option value="IEPS">IEPS (003)</option>
                  </select>
                </td>

                <td class="px-3 py-2">
                  <select class="form-select" x-model="it.factor" @change="recalcModal()">
                    <option value="Tasa">Tasa</option>
                    <option value="Cuota">Cuota</option>
                    <option value="Exento">Exento</option>
                  </select>
                </td>

                <td class="px-3 py-2 text-right">
                  <input type="number" step="0.01" min="0"
                         class="form-input w-24 text-right"
                         x-model.number="it.tasa"
                         :disabled="it.factor==='Exento'"
                         @input.debounce.150ms="recalcModal()">
                </td>

                <td class="px-3 py-2 text-right">
                  <input type="number" step="0.01" min="0"
                         class="form-input w-28 text-right"
                         x-model.number="it.base"
                         @input.debounce.150ms="recalcModal()">
                </td>

                <td class="px-3 py-2 text-right">
                  <div class="font-semibold" :class="normalizeTipoImpuesto(it.tipo) === 'R' ? 'text-red-600 dark:text-red-400' : ''" x-text="moneySigned(it.importe, it.tipo)"></div>
                </td>

                <td class="px-3 py-2 text-right">
                  <button type="button"
                          class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-600 text-white hover:bg-red-700"
                          title="Quitar"
                          @click="eliminarImpuesto(i)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </td>
              </tr>
            </template>

            <tr x-show="impuestosEdit.length===0">
              <td colspan="7" class="px-3 py-4 text-center text-gray-500">Sin impuestos.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between mt-4">
        <button type="button"
                class="btn border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200"
                @click="agregarImpuesto()">
          + Agregar impuesto
        </button>

        <div class="flex items-center gap-2">
          <button type="button"
                  class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200"
                  @click="cerrarImpuestos()">
            Cancelar
          </button>

          <button type="button"
                  class="btn btn-primary"
                  @click="guardarImpuestos()">
            Guardar
          </button>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- =========================
     Alpine Component
   ========================= --}}
<script>
  window.complementoCreate = (opts) => ({
    opts,
    isSubmitting: false,

    // UI states
    ui: {
      cliente: { q: '', open: false, items: [] },
      fact: { q: '', open: false, items: [] }
    },

    // data pools
    pool: {
      clientes: Array.isArray(opts.clientes) ? opts.clientes : [],
      pendientes: [],
      foliosPago: Array.isArray(opts.foliosPago) ? opts.foliosPago : [],
    },

    clienteSel: null,

    // form
    form: {
      cliente_id: '',
      fecha_documento: '',
      fecha_pago: '',

      // folio complemento
      serie_pago: '',
      folio_pago: null,

      forma_pago_p: '03',
      moneda_p: 'MXN',
      tipo_cambio_p: 1,

      // bancarios + operación
      num_operacion: '',
      rfc_banco_emisor: '',
      cuenta_ordenante: '',
      banco_receptor: '',
      cuenta_beneficiaria: '',

      pagos: []
    },

    // totals
    totales: { subtotal: 0, traslados: 0, retenciones: 0, total: 0 },

    // impuestos modal
    modalImpuestos: { open:false, idx:-1 },
    impuestosEdit: [],

    // ===== Helpers =====
    uid(){
      return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Math.random().toString(36).slice(2);
    },
    money(n){
      n = Number(n || 0);
      return n.toLocaleString('es-MX', { style:'currency', currency:'MXN' });
    },
    normalizeTipoImpuesto(tipo){
      const t = String(tipo ?? '').trim().toUpperCase();
      if (t === 'R' || t === 'RET' || t === 'RETENCION' || t === 'RETENCIÓN') return 'R';
      return 'T';
    },
    moneySigned(n, tipo = 'T'){
      const amount = this.money(Math.abs(Number(n || 0)));
      return `${this.normalizeTipoImpuesto(tipo) === 'R' ? '-' : '+'}${amount}`;
    },
    basePago(p){
      if (!p || !p.objeto_imp || !Array.isArray(p.impuestos) || !p.impuestos.length) {
        return Math.round(Number(p?.monto_pago || 0) * 100) / 100;
      }

      const bases = p.impuestos
        .map(it => Math.round(Number(it?.base || 0) * 100) / 100)
        .filter(base => base > 0);

      if (!bases.length) {
        return Math.round(Number(p.monto_pago || 0) * 100) / 100;
      }

      return Math.max(...bases);
    },
    defaultTaxBase(p, it = {}){
      const monto = Math.max(Number(p?.monto_pago || 0), 0);
      const tipo = this.normalizeTipoImpuesto(it.tipo || 'T');
      const impuesto = String(it.impuesto || 'IVA').toUpperCase();
      const factor = String(it.factor || 'Tasa');
      const tasa = Number(it.tasa ?? 16);

      if (tipo === 'T' && impuesto === 'IVA' && factor === 'Tasa' && Math.abs(tasa - 16) < 0.000001) {
        return Math.round((monto / 1.16) * 100) / 100;
      }

      return Math.round(monto * 100) / 100;
    },

    // ===== Init =====
    init(){
      const p = this.opts.prefill && typeof this.opts.prefill === 'object' ? this.opts.prefill : null;

      // Fecha default now
      const now = new Date();
      const pad = (x) => String(x).padStart(2,'0');
      const local = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

      this.form.fecha_documento = (p?.fecha_documento) ? p.fecha_documento : ((p?.fecha_pago) ? p.fecha_pago : local);
      this.form.fecha_pago = (p?.fecha_pago) ? p.fecha_pago : ((p?.fecha_documento) ? p.fecha_documento : local);

      // Cliente
      if (p?.cliente_id) {
        this.form.cliente_id = String(p.cliente_id);
        const c = this.pool.clientes.find(x => Number(x.id) === Number(p.cliente_id));
        if (c) this.selectCliente(c, false);
      } else {
        this.ui.cliente.items = this.pool.clientes.slice(0, 30);
      }

      // Folio complemento
      if (p?.serie_pago) this.form.serie_pago = p.serie_pago;
      if (p?.folio_pago) this.form.folio_pago = Number(p.folio_pago);

      if (!this.form.serie_pago && this.pool.foliosPago.length) {
        this.form.serie_pago = this.pool.foliosPago[0].serie;
        this.form.folio_pago = Number(this.pool.foliosPago[0].siguiente || 1);
      } else if (this.form.serie_pago && (!this.form.folio_pago || Number(this.form.folio_pago) <= 0)) {
        this.onSeriePagoChange();
      }

      // defaults pago
      if (p?.forma_pago_p) this.form.forma_pago_p = p.forma_pago_p;
      if (p?.moneda_p) this.form.moneda_p = p.moneda_p;
      if (p?.tipo_cambio_p) this.form.tipo_cambio_p = Number(p.tipo_cambio_p || 1);

      // bancarios
      if (p?.num_operacion) this.form.num_operacion = p.num_operacion;
      if (p?.rfc_banco_emisor) this.form.rfc_banco_emisor = p.rfc_banco_emisor;
      if (p?.cuenta_ordenante) this.form.cuenta_ordenante = p.cuenta_ordenante;
      if (p?.banco_receptor) this.form.banco_receptor = p.banco_receptor;
      if (p?.cuenta_beneficiaria) this.form.cuenta_beneficiaria = p.cuenta_beneficiaria;

      // pagos prefill
      if (Array.isArray(p?.pagos)) {
        this.form.pagos = p.pagos.map(d => ({
          uid: this.uid(),
          factura_id: d.factura_id ?? null,
          uuid: d.uuid ?? '',
          serie_folio: d.serie_folio ?? '',
          nombre: d.nombre ?? '',
          moneda_dr: d.moneda_dr ?? 'MXN',
          metodo_pago_dr: d.metodo_pago_dr ?? 'PPD',
          num_parcialidad: Number(d.num_parcialidad ?? 1),
          saldo_anterior: Number(d.saldo_anterior ?? 0),
          monto_pago: Number(d.monto_pago ?? 0),
          saldo_insoluto: Number(d.saldo_insoluto ?? 0),
          objeto_imp: !!d.objeto_imp,
          impuestos: Array.isArray(d.impuestos) ? d.impuestos : [],
        }));
      }

      this.recalcular();
    },

    onSeriePagoChange(){
      const s = (this.pool.foliosPago || []).find(x => x.serie === this.form.serie_pago);
      if (s) this.form.folio_pago = Number(s.siguiente || 1);
    },

    // ===== Clientes (buscable) =====
    filtrarClientes(){
      const q = (this.ui.cliente.q || '').trim().toLowerCase();
      const all = this.pool.clientes || [];
      if (!q) {
        this.ui.cliente.items = all.slice(0, 40);
        this.ui.cliente.open = true;
        return;
      }
      this.ui.cliente.items = all.filter(c => {
        const rs = String(c.razon_social || '').toLowerCase();
        const rfc = String(c.rfc || '').toLowerCase();
        return rs.includes(q) || rfc.includes(q);
      }).slice(0, 80);
      this.ui.cliente.open = true;
    },

    async selectCliente(c, recargar = true){
      this.form.cliente_id = String(c.id);
      this.clienteSel = c;
      this.ui.cliente.q = `${c.razon_social || ''} — ${c.rfc || ''}`.trim();
      this.ui.cliente.open = false;

      // reset facturas capturadas al cambiar cliente
      this.form.pagos = [];
      this.recalcular();

      if (recargar) await this.recargarPendientes();
    },

    // ===== Pendientes (facturas) =====
    async recargarPendientes(){
      if (!this.form.cliente_id) return;

      try {
        const url = new URL(this.opts.endpoints.facturasPendientes, window.location.origin);
        url.searchParams.set('cliente_id', String(this.form.cliente_id));

        const r = await fetch(url.toString(), { headers: { 'Accept':'application/json' }});
        const j = await r.json().catch(()=>[]);
        this.pool.pendientes = Array.isArray(j) ? j : [];
      } catch(e){
        console.error(e);
        this.pool.pendientes = [];
      }

      this.filtrarPendientes();
    },

    filtrarPendientes(){
      const q = (this.ui.fact.q || '').trim().toLowerCase();
      const all = this.pool.pendientes || [];

      const used = new Set((this.form.pagos||[]).map(x => String(x.uuid || '').toLowerCase()));
      let items = all.filter(x => !used.has(String(x.uuid || '').toLowerCase()));

      if (q) {
        items = items.filter(f => {
          const s = `${f.uuid||''} ${(f.serie||'')}-${(f.folio||'')} ${f.razon_social||''} ${f.rfc||''}`.toLowerCase();
          return s.includes(q);
        });
      }

      this.ui.fact.items = items.slice(0, 80);
      this.ui.fact.open = true;
    },

    agregarFactura(f){
      if (!f || !f.uuid) return;

      const saldo = Number(f.saldo_insoluto ?? 0);
      const monto = saldo;
      const saldoInsol = Math.max(saldo - monto, 0);

      this.form.pagos.push({
        uid: this.uid(),
        factura_id: f.id ?? null,
        uuid: String(f.uuid),
        serie_folio: (f.serie && f.folio) ? `${f.serie}-${f.folio}` : `#${f.id ?? ''}`,
        nombre: f.razon_social || '',
        moneda_dr: f.moneda_dr || 'MXN',
        metodo_pago_dr: f.metodo_pago_dr || 'PPD',
        num_parcialidad: Number(f.num_parcialidad ?? 1),
        saldo_anterior: saldo,
        monto_pago: monto,
        saldo_insoluto: saldoInsol,
        objeto_imp: false,
        impuestos: []
      });

      // ✅ refresco tipo "recargar": quita del pool la agregada y vuelve a filtrar
      const u = String(f.uuid || '').toLowerCase();
      this.pool.pendientes = (this.pool.pendientes || []).filter(x => String(x.uuid || '').toLowerCase() !== u);

      this.ui.fact.q = '';
      this.ui.fact.open = true;

      this.recalcular();
      this.filtrarPendientes();
    },

    quitarFactura(idx){
      this.form.pagos.splice(idx, 1);
      this.recalcular();
      this.filtrarPendientes();
    },

    // ===== Impuestos =====
    recalcImpuestosRow(p){
      if (!p || !Array.isArray(p.impuestos)) return;

      for (const it of p.impuestos) {
        // Si base no está definida (o 0), la amarramos al monto pago actual
        if (it.base == null || Number(it.base) === 0) it.base = this.defaultTaxBase(p, it);

        const base = Number(it.base || 0);

        if (String(it.factor) === 'Exento') {
          it.importe = 0;
          continue;
        }

        const tasa = Number(it.tasa || 0) / 100;
        const imp = base * tasa;
        it.importe = Math.round(imp * 100) / 100;
      }
    },

    onToggleObjetoImp(idx){
      const p = this.form.pagos[idx];
      if (!p) return;

      if (!p.objeto_imp) {
        p.impuestos = [];
      } else {
        if (!Array.isArray(p.impuestos) || !p.impuestos.length) {
          p.impuestos = [{
            uid: this.uid(),
            tipo: 'T',
            impuesto: 'IVA',
            factor: 'Tasa',
            tasa: 16,
            base: this.defaultTaxBase(p, { tipo:'T', impuesto:'IVA', factor:'Tasa', tasa:16 }),
            importe: 0,
          }];
        }
        // ✅ calcula importes sin abrir modal
        this.recalcImpuestosRow(p);
      }

      this.recalcular();
    },

    // ===== Modal Impuestos =====
    abrirImpuestos(idx){
      const p = this.form.pagos[idx];
      if (!p || !p.objeto_imp) return;

      this.modalImpuestos.open = true;
      this.modalImpuestos.idx = idx;

      const arr = Array.isArray(p.impuestos) ? p.impuestos : [];
      this.impuestosEdit = arr.map(x => ({
        uid: x.uid || this.uid(),
        tipo: this.normalizeTipoImpuesto(x.tipo),
        impuesto: x.impuesto || 'IVA',
        factor: x.factor || 'Tasa',
        tasa: Number(x.tasa ?? 16),
        base: Number(x.base ?? this.defaultTaxBase(p, x)),
        importe: Number(x.importe ?? 0),
      }));

      if (!this.impuestosEdit.length) {
        this.impuestosEdit.push({
          uid: this.uid(),
          tipo: 'T',
          impuesto: 'IVA',
          factor: 'Tasa',
          tasa: 16,
          base: this.defaultTaxBase(p, { tipo:'T', impuesto:'IVA', factor:'Tasa', tasa:16 }),
          importe: 0,
        });
      }

      this.recalcModal();
    },

    cerrarImpuestos(){
      this.modalImpuestos.open = false;
      this.modalImpuestos.idx = -1;
      this.impuestosEdit = [];
    },

    agregarImpuesto(){
      this.impuestosEdit.push({
        uid: this.uid(),
        tipo: 'T',
        impuesto: 'IVA',
        factor: 'Tasa',
        tasa: 16,
        base: 0,
        importe: 0,
      });
      this.recalcModal();
    },

    eliminarImpuesto(i){
      this.impuestosEdit.splice(i,1);
      this.recalcModal();
    },

    recalcModal(){
      for (const it of (this.impuestosEdit||[])) {
        const base = Number(it.base || 0);
        if (String(it.factor) === 'Exento') {
          it.importe = 0;
          continue;
        }
        const tasa = Number(it.tasa || 0) / 100;
        const imp = base * tasa;
        it.importe = Math.round(imp * 100) / 100;
      }
    },

    guardarImpuestos(){
      const idx = this.modalImpuestos.idx;
      const p = this.form.pagos[idx];
      if (!p) return;

      p.impuestos = (this.impuestosEdit || []).map(it => ({
        uid: it.uid || this.uid(),
        tipo: this.normalizeTipoImpuesto(it.tipo),
        impuesto: it.impuesto || 'IVA',
        factor: it.factor || 'Tasa',
        tasa: Number(it.tasa || 0),
        base: Number(it.base || 0),
        importe: Number(it.importe || 0),
      }));

      // ✅ asegura importes y totales al guardar
      this.recalcImpuestosRow(p);

      this.cerrarImpuestos();
      this.recalcular();
    },

    resumenImpuestos(p){
      if (!p || !p.objeto_imp) return '—';
      const arr = Array.isArray(p.impuestos) ? p.impuestos : [];
      if (!arr.length) return 'Sin impuestos';
      return arr.map(i => {
        const tipo = this.normalizeTipoImpuesto(i.tipo);
        const t = (tipo === 'R') ? 'Ret' : 'Tras';
        const imp = i.impuesto || 'IVA';
        const tasa = (i.factor === 'Exento') ? 'Exento' : `${Number(i.tasa||0).toFixed(2)}%`;
        const impMon = this.moneySigned(i.importe || 0, tipo);
        return `${t} ${imp} ${tasa} (${impMon})`;
      }).join(', ');
    },

    // ===== Totales =====
    recalcular(){
      let total = 0, tras = 0, ret = 0, subtotal = 0;

      for (const p of (this.form.pagos || [])) {
        const saldoAnt = Number(p.saldo_anterior || 0);
        const pagado   = Math.max(Number(p.monto_pago || 0), 0);
        p.monto_pago = pagado;

        p.saldo_anterior = Math.max(Math.round(saldoAnt * 100) / 100, 0);
        p.saldo_insoluto = Math.max(Math.round(Number(p.saldo_insoluto || 0) * 100) / 100, 0);

        total += pagado;

        if (p.objeto_imp && Array.isArray(p.impuestos)) {
          // ✅ recalcula importes sin depender del modal
          this.recalcImpuestosRow(p);
          subtotal += this.basePago(p);

          for (const it of p.impuestos) {
            const imp = Number(it.importe || 0);
            if (this.normalizeTipoImpuesto(it.tipo) === 'R') ret += imp;
            else tras += imp;
          }
        } else {
          subtotal += pagado;
        }
      }

      total = Math.round(total * 100) / 100;
      subtotal = Math.round(subtotal * 100) / 100;
      tras  = Math.round(tras * 100) / 100;
      ret   = Math.round(ret * 100) / 100;

      this.totales = {
        subtotal,
        traslados: tras,
        retenciones: ret,
        total,
      };
    },

    // ===== Actions =====
    guardarBorrador(){
      alert('Guardar borrador: pendiente (si quieres lo cableamos a BD).');
    },

    previsualizar(){
      if (!this.form.cliente_id) { alert('Selecciona un cliente'); return; }
      if (!this.form.fecha_documento) { alert('Captura la fecha del documento'); return; }
      if (!this.form.fecha_pago) { alert('Captura la fecha de pago'); return; }
      if (!Array.isArray(this.form.pagos) || !this.form.pagos.length) {
        alert('Agrega al menos una factura pendiente');
        return;
      }

      if (!this.form.serie_pago) { alert('Selecciona una serie para el complemento'); return; }
      if (!Number(this.form.folio_pago || 0)) { alert('Captura un folio válido para el complemento'); return; }

      for (const p of this.form.pagos) {
        if (!p.uuid) { alert('Hay una fila sin UUID'); return; }
        if (Number(p.monto_pago || 0) <= 0) { alert('Hay una factura con Monto pago = 0'); return; }
      }

      this.isSubmitting = true;

      const payload = {
        cliente_id: Number(this.form.cliente_id),
        fecha_documento: this.form.fecha_documento,
        fecha_pago: this.form.fecha_pago,

        // folio complemento
        serie_pago: this.form.serie_pago,
        folio_pago: Number(this.form.folio_pago || 0),

        forma_pago_p: this.form.forma_pago_p,
        moneda_p: this.form.moneda_p,
        tipo_cambio_p: Number(this.form.tipo_cambio_p || 1),

        // bancarios
        num_operacion: this.form.num_operacion,
        rfc_banco_emisor: this.form.rfc_banco_emisor,
        cuenta_ordenante: this.form.cuenta_ordenante,
        banco_receptor: this.form.banco_receptor,
        cuenta_beneficiaria: this.form.cuenta_beneficiaria,

        pagos: this.form.pagos.map(p => ({
          factura_id: p.factura_id,
          uuid: p.uuid,
          serie_folio: p.serie_folio,
          moneda_dr: p.moneda_dr,
          metodo_pago_dr: p.metodo_pago_dr,
          num_parcialidad: Number(p.num_parcialidad || 1),
          saldo_anterior: Number(p.saldo_anterior || 0),
          monto_pago: Number(p.monto_pago || 0),
          saldo_insoluto: Number(p.saldo_insoluto || 0),

          objeto_imp: !!p.objeto_imp,
          impuestos: (Array.isArray(p.impuestos) ? p.impuestos : []).map(it => ({
            tipo: it.tipo || 'T',
            impuesto: it.impuesto || 'IVA',
            factor: it.factor || 'Tasa',
            tasa: Number(it.tasa || 0),
            base: Number(it.base || 0),
            importe: Number(it.importe || 0),
          })),
        })),

        _ui_totales: this.totales,
      };

      this.$refs.payload.value = JSON.stringify(payload);
      this.$nextTick(() => this.$refs.previewForm.submit());
    },
  });
</script>
@endsection
