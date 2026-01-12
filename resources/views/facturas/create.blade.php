@extends('layouts.app')

@section('title','Nueva Factura')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">

  {{-- Encabezado + RFC activo --}}
  <div class="flex items-start justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Nueva Factura</h1>
    <div class="flex items-center gap-2">
      <span class="text-xs uppercase text-gray-400 dark:text-gray-500">RFC activo</span>
      <div class="px-2 py-1 rounded bg-violet-500/10 text-violet-600 dark:text-violet-400 text-sm">
        {{ $rfcActivo ?? '—' }}
      </div>
    </div>
  </div>

  @php
    $rfcUsuarioId = $rfcUsuarioId ?? (session('rfc_usuario_id') ?? session('rfc_activo_id') ?? auth()->id() ?? 0);

    $clientesArr = ($clientes ?? collect())->map(function ($c) {
      return [
        'id'            => $c->id,
        'rfc'           => $c->rfc,
        'razon_social'  => $c->razon_social,
        'calle'         => $c->calle,
        'no_ext'        => $c->no_ext,
        'no_int'        => $c->no_int,
        'colonia'       => $c->colonia,
        'localidad'     => $c->localidad,
        'estado'        => $c->estado,
        'codigo_postal' => $c->codigo_postal,
        'pais'          => $c->pais,
        'email'         => $c->email,
      ];
    })->values()->all();

    $minFecha = $minFecha ?? now()->copy()->subHours(72)->format('Y-m-d\TH:i');
    $maxFecha = $maxFecha ?? now()->format('Y-m-d\TH:i');

    // Fallback por si el controller aún no manda listas (igual te digo abajo cómo mandarlas)
    $metodosPago = $metodosPago ?? [
      ['clave'=>'PUE','descripcion'=>'Pago en una sola exhibición'],
      ['clave'=>'PPD','descripcion'=>'Pago en parcialidades o diferido'],
    ];
    $formasPago = $formasPago ?? [
      ['clave'=>'01','descripcion'=>'Efectivo'],
      ['clave'=>'02','descripcion'=>'Cheque nominativo'],
      ['clave'=>'03','descripcion'=>'Transferencia electrónica de fondos'],
      ['clave'=>'04','descripcion'=>'Tarjeta de crédito'],
      ['clave'=>'28','descripcion'=>'Tarjeta de débito'],
      ['clave'=>'99','descripcion'=>'Por definir'],
    ];
  @endphp

  <script>
    window.__FACTURA_CREATE_OPTS__ = {
      csrf: @json(csrf_token()),
      rfcUsuarioId: @json((int)$rfcUsuarioId),
      rfcActivo: @json($rfcActivo ?? ''),
      clientes: @json($clientesArr, JSON_UNESCAPED_UNICODE),
      minFecha: @json($minFecha),
      maxFecha: @json($maxFecha),
      prefill: @json($prefill ?? [], JSON_UNESCAPED_UNICODE),

      endpoints: {
        nextFolio: @json(url('/api/series/next')),
        buscarProductos: @json(url('/api/productos/buscar')),
        satProdServ: @json(url('/api/sat/clave-prod-serv')),
        satUnidad: @json(url('/api/sat/clave-unidad')),
        clienteUpdateJsonBase: @json(url('/catalogos/clientes')), // POST /catalogos/clientes/{id}
      },

      routePreview: @json(route('facturas.preview')),

      // catálogos para selects
      metodosPago: @json($metodosPago, JSON_UNESCAPED_UNICODE),
      formasPago: @json($formasPago, JSON_UNESCAPED_UNICODE),
    };
  </script>

  <div x-data="facturaCreate(window.__FACTURA_CREATE_OPTS__)" x-init="init()" class="space-y-6">

    {{-- FORM oculto para enviar payload al preview --}}
    <form method="POST" :action="opts.routePreview" x-ref="previewForm">
      @csrf
      <input type="hidden" name="payload" x-ref="payload">
    </form>

    {{-- DATOS DEL COMPROBANTE --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos del comprobante</h2>
        <div class="flex gap-2">
          <a href="{{ route('facturas.index') }}"
             class="btn border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-700 dark:text-gray-200">
            Volver
          </a>
          <button type="button"
                  class="btn bg-violet-600 hover:bg-violet-700 text-white"
                  @click="previsualizar()"
                  :disabled="isSubmitting">
            <span x-show="!isSubmitting">Previsualizar</span>
            <span x-show="isSubmitting">Generando…</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de comprobante</label>
          <select x-model="form.tipo_comprobante" @change="onTipoComprobanteChange()" class="form-select w-full">
            <option value="I">Ingreso</option>
            <option value="E">Egreso</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Serie</label>
          <input type="text" x-model="form.serie" class="form-input w-full" readonly>
        </div>

        <div>
          <div class="flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Folio</label>
            <button type="button" class="text-xs text-violet-600 hover:text-violet-700" @click="pedirSiguienteFolio()">
              Actualizar
            </button>
          </div>
          <input type="text" x-model="form.folio" class="form-input w-full" readonly>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y hora</label>
          <input type="datetime-local"
                 class="form-input w-full"
                 :min="minFecha" :max="maxFecha"
                 x-model="form.fecha"
                 @change="clampFecha()">
          <p class="text-xs text-gray-500 mt-1">La fecha debe estar dentro de las últimas 72 horas.</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Método de pago</label>
          <select x-model="form.metodo_pago" class="form-select w-full">
            <template x-for="m in metodosPago" :key="m.clave">
              <option :value="m.clave" x-text="m.clave + ' — ' + m.descripcion"></option>
            </template>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de pago</label>
          <select x-model="form.forma_pago" class="form-select w-full">
            <template x-for="f in formasPago" :key="f.clave">
              <option :value="f.clave" x-text="f.clave + ' — ' + f.descripcion"></option>
            </template>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Uso CFDI</label>
          <select x-model="form.uso_cfdi" class="form-select w-full">
            <option value="">— Selecciona —</option>
            <option value="G01">G01 — Adquisición de mercancías</option>
            <option value="G02">G02 — Devoluciones, descuentos o bonificaciones</option>
            <option value="G03">G03 — Gastos en general</option>
            <option value="I01">I01 — Construcciones</option>
            <option value="I02">I02 — Mobiliario y equipo de oficina</option>
            <option value="I03">I03 — Equipo de transporte</option>
            <option value="I04">I04 — Equipo de cómputo y accesorios</option>
            <option value="I05">I05 — Dados, troqueles, moldes, matrices y herramental</option>
            <option value="I06">I06 — Comunicaciones telefónicas</option>
            <option value="I07">I07 — Comunicaciones satelitales</option>
            <option value="I08">I08 — Otra maquinaria y equipo</option>
            <option value="D01">D01 — Honorarios médicos, dentales y hospitalarios</option>
            <option value="D02">D02 — Gastos médicos por incapacidad o discapacidad</option>
            <option value="D03">D03 — Gastos funerales</option>
            <option value="D04">D04 — Donativos</option>
            <option value="D05">D05 — Intereses reales por créditos hipotecarios</option>
            <option value="D06">D06 — Aportaciones voluntarias al SAR</option>
            <option value="D07">D07 — Primas por seguros de gastos médicos</option>
            <option value="D08">D08 — Transportación escolar obligatoria</option>
            <option value="D09">D09 — Depósitos en cuentas para ahorro / planes personales</option>
            <option value="D10">D10 — Servicios educativos (colegiaturas)</option>
            <option value="S01">S01 — Sin efectos fiscales</option>
          </select>
          <p class="text-xs text-gray-500 mt-1">Este valor es obligatorio para timbrar.</p>
        </div>

        {{-- Exportación (como iKontrol) --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Exportación</label>
          <select x-model="form.exportacion" class="form-select w-full" @change="recalcularTotales()">
            <option value="01">01 — No aplica</option>
            <option value="02">02 — Definitiva</option>
          </select>
        </div>

        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios en PDF</label>
          <textarea rows="2" class="form-input w-full" x-model="form.comentarios_pdf"
                    placeholder="Comentarios o notas visibles en el PDF"></textarea>
        </div>

        <div class="md:col-span-3">
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" class="form-checkbox" x-model="form.complemento_exportacion.habilitar">
            Habilitar complemento de exportación
          </label>

          <div class="mt-2" x-show="form.complemento_exportacion.habilitar" style="display:none">
            <label class="block text-xs text-gray-500 mb-1">Observación</label>
            <textarea rows="2" class="form-input w-full"
                      x-model="form.complemento_exportacion.observacion"
                      placeholder="Observación de exportación"></textarea>
          </div>
        </div>

      </div>
    </div>

    {{-- CLIENTE --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Cliente</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Selecciona cliente</label>
          <div class="flex gap-2">
            <div class="flex-1">
              <select x-model="form.cliente_id" @change="onClienteChange()" class="form-select w-full">
                <option value="">— Selecciona —</option>
                <template x-for="c in clientes" :key="c.id">
                  <option :value="String(c.id)" x-text="`${c.razon_social} — ${c.rfc}`"></option>
                </template>
              </select>
            </div>

            <button type="button"
                    class="btn-sm bg-violet-500 hover:bg-violet-600 text-white"
                    @click="drawerClienteOpen = true"
                    :disabled="!form.cliente_id">
              Actualizar
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-1 gap-x-6 text-sm mt-3">
        <div><span class="text-gray-400">Razón social:</span> <span class="font-medium" x-text="clienteSel.razon_social || '—'"></span></div>
        <div><span class="text-gray-400">RFC:</span> <span class="font-medium" x-text="clienteSel.rfc || '—'"></span></div>
        <div><span class="text-gray-400">Correo:</span> <span class="font-medium" x-text="clienteSel.email || '—'"></span></div>
        <div><span class="text-gray-400">Calle:</span> <span class="font-medium" x-text="clienteSel.calle || '—'"></span></div>
        <div><span class="text-gray-400">No. ext:</span> <span class="font-medium" x-text="clienteSel.no_ext || '—'"></span></div>
        <div><span class="text-gray-400">No. int:</span> <span class="font-medium" x-text="clienteSel.no_int || '—'"></span></div>
        <div><span class="text-gray-400">Colonia:</span> <span class="font-medium" x-text="clienteSel.colonia || '—'"></span></div>
        <div><span class="text-gray-400">Localidad:</span> <span class="font-medium" x-text="clienteSel.localidad || '—'"></span></div>
        <div><span class="text-gray-400">Estado:</span> <span class="font-medium" x-text="clienteSel.estado || '—'"></span></div>
        <div><span class="text-gray-400">País:</span> <span class="font-medium" x-text="clienteSel.pais || '—'"></span></div>
        <div><span class="text-gray-400">C.P.:</span> <span class="font-medium" x-text="clienteSel.codigo_postal || '—'"></span></div>
      </div>
    </div>

    {{-- CONCEPTOS --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Conceptos</h2>

        <div class="flex items-end gap-2">
          <div>
            <label class="block text-xs text-gray-500">Buscar producto</label>
            <input type="text" class="form-input w-72" placeholder="Código o descripción"
                   x-model="buscaProd.query" @input.debounce.250ms="buscarProductoGlobal()">
          </div>

          <div>
            <label class="block text-xs text-gray-500">Resultados</label>
            <select size="1" class="form-select w-72"
                    x-model="buscaProd.selectedId"
                    @change="agregarProductoDesdeBuscador()">
              <option value="">— Selecciona —</option>
              <template x-for="p in buscaProd.suggestions" :key="p.id">
                <option :value="String(p.id)" x-text="`${p.clave || ''} ${p.descripcion}`"></option>
              </template>
            </select>
          </div>

          <button type="button"
                  class="btn-sm bg-gray-900 dark:bg-gray-700 text-white hover:opacity-90"
                  @click="agregarConcepto()">
            Agregar concepto vacío
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="table-auto w-full text-sm">
          <thead class="text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700/60">
            <tr>
              <th class="px-2 py-2 text-left">Descripción</th>
              <th class="px-2 py-2 text-left">Clave ProdServ</th>
              <th class="px-2 py-2 text-left">Clave Unidad</th>
              <th class="px-2 py-2 text-left">Unidad</th>
              <th class="px-2 py-2 text-right">Cantidad</th>
              <th class="px-2 py-2 text-right">Precio</th>
              <th class="px-2 py-2 text-right">Desc.</th>
              <th class="px-2 py-2 text-right">Impuestos</th>
              <th class="px-2 py-2 text-right">Importe</th>
              <th class="px-2 py-2"></th>
            </tr>
          </thead>

          <tbody>
            <template x-for="(row, idx) in form.conceptos" :key="row.uid">
              <tr class="border-b border-gray-100 dark:border-gray-700/50 align-top">
                <td class="px-2 py-2">
                  <textarea rows="1" class="form-input w-full" x-model="row.descripcion" @input="recalcularTotales()"></textarea>
                </td>

                <td class="px-2 py-2">
                  <div class="flex gap-2">
                    <input type="text" class="form-input w-full" x-model="row.clave_prod_serv" @input="recalcularTotales()">
                    <button type="button" class="btn-xs bg-gray-100 dark:bg-gray-700" @click="abrirSat(idx,'prodserv')">SAT</button>
                  </div>
                </td>

                <td class="px-2 py-2">
                  <div class="flex gap-2">
                    <input type="text" class="form-input w-full" x-model="row.clave_unidad" @input="recalcularTotales()">
                    <button type="button" class="btn-xs bg-gray-100 dark:bg-gray-700" @click="abrirSat(idx,'unidad')">SAT</button>
                  </div>
                </td>

                <td class="px-2 py-2">
                  <input type="text" class="form-input w-full" x-model="row.unidad" @input="recalcularTotales()">
                </td>

                <td class="px-2 py-2">
                  <input type="number" min="0" step="0.01" class="form-input w-28 text-right"
                         x-model.number="row.cantidad" @input="recalcularTotales()">
                </td>

                <td class="px-2 py-2">
                  <input type="number" min="0" step="0.01" class="form-input w-28 text-right"
                         x-model.number="row.precio" @input="recalcularTotales()">
                </td>

                <td class="px-2 py-2">
                  <input type="number" min="0" step="0.01" class="form-input w-24 text-right"
                         x-model.number="row.descuento" @input="recalcularTotales()">
                </td>

                <td class="px-2 py-2 text-right">
                  <div class="flex flex-col items-end gap-1">
                    <button type="button" class="btn-xs bg-violet-500/10 text-violet-700 dark:text-violet-300"
                            @click="abrirImpuestos(idx)">
                      Editar
                    </button>
                    <div class="text-xs text-gray-500" x-text="resumenImpuestos(row) || '—'"></div>

                    {{-- Compatibilidad simple: checkbox IVA 16 si no usa modal --}}
                    <label class="inline-flex items-center gap-2 text-xs text-gray-500 mt-1">
                      <input type="checkbox" class="form-checkbox"
                             x-model="row.aplica_iva"
                             @change="recalcularTotales()">
                      IVA 16%
                    </label>
                  </div>
                </td>

                <td class="px-2 py-2 text-right font-medium">
                  <div x-text="money(importeRow(row))"></div>
                </td>

                <td class="px-2 py-2 text-right">
                  <button type="button" class="text-red-500 hover:text-red-600" @click="eliminarConcepto(idx)">✕</button>
                </td>
              </tr>
            </template>

            <tr x-show="form.conceptos.length===0" style="display:none">
              <td colspan="10" class="py-6 text-center text-gray-500">Agrega conceptos buscando productos.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- RELACIONADOS --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">CFDI Relacionados</h2>
        <button type="button" class="btn-sm bg-gray-100 dark:bg-gray-700" @click="agregarRelacionado()">+ Agregar</button>
      </div>

      <div class="space-y-3">
        <template x-for="(r, i) in form.relacionados" :key="r.uid">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end border border-gray-200 dark:border-gray-700/60 rounded-lg p-3">
            <div class="md:col-span-3">
              <label class="text-xs text-gray-500">Tipo relación</label>
              <select class="form-select w-full" x-model="r.tipo_relacion">
                <option value="">—</option>
                <option value="01">01 — Nota de crédito</option>
                <option value="02">02 — Nota de débito</option>
                <option value="03">03 — Devolución</option>
                <option value="04">04 — Sustitución</option>
                <option value="07">07 — CFDI por aplicación de anticipo</option>
              </select>
            </div>
            <div class="md:col-span-8">
              <label class="text-xs text-gray-500">UUID</label>
              <input class="form-input w-full" x-model="r.uuid" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX">
            </div>
            <div class="md:col-span-1 text-right">
              <button type="button" class="text-red-500 hover:text-red-600" @click="eliminarRelacionado(i)">✕</button>
            </div>
          </div>
        </template>

        <div class="text-sm text-gray-500" x-show="form.relacionados.length===0" style="display:none">
          Sin relacionados.
        </div>
      </div>
    </div>

    {{-- TOTALES + IMPUESTOS LOCALES --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Totales</h2>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="space-y-2">
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" class="form-checkbox" x-model="form.impuestos_locales.ret_5_millar" @change="recalcularTotales()">
            Retención 5 al millar
          </label>

          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" class="form-checkbox" x-model="form.impuestos_locales.ret_cedular_2" @change="recalcularTotales()">
            Retención Cédular 2%
          </label>

          <p class="text-xs text-gray-500">Estas retenciones se aplican sobre (subtotal - descuento).</p>
        </div>

        <div class="lg:col-span-2">
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">Subtotal</span>
              <span class="font-medium" x-text="money(totales.subtotal)"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Descuento</span>
              <span class="font-medium" x-text="money(totales.descuento)"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Impuestos</span>
              <span class="font-medium" x-text="money(totales.impuestos)"></span>
            </div>
            <div class="flex justify-between" x-show="totales.ret_local_total > 0" style="display:none">
              <span class="text-gray-500">Retenciones locales</span>
              <span class="font-medium" x-text="money(totales.ret_local_total)"></span>
            </div>

            <div class="pt-2 border-t border-gray-200 dark:border-gray-700/60 flex justify-between">
              <span class="text-base font-semibold">Total</span>
              <span class="text-lg font-semibold" x-text="money(totales.total)"></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- DRAWER EDITAR CLIENTE --}}
    <div x-show="drawerClienteOpen" style="display:none" class="fixed inset-0 z-40">
      <div class="absolute inset-0 bg-black/40" @click="drawerClienteOpen=false"></div>

      <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl z-50 overflow-y-auto"
           @click.stop>
        <div class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Actualizar cliente</h3>
            <button class="text-gray-500 hover:text-gray-700" @click="drawerClienteOpen=false">✕</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
              <label class="text-xs text-gray-500">Razón social</label>
              <input class="form-input w-full" x-model="clienteEdit.razon_social">
            </div>
            <div>
              <label class="text-xs text-gray-500">RFC</label>
              <input class="form-input w-full" x-model="clienteEdit.rfc">
            </div>
            <div>
              <label class="text-xs text-gray-500">Email</label>
              <input class="form-input w-full" x-model="clienteEdit.email">
            </div>
            <div class="sm:col-span-2">
              <label class="text-xs text-gray-500">Calle</label>
              <input class="form-input w-full" x-model="clienteEdit.calle">
            </div>
            <div>
              <label class="text-xs text-gray-500">No ext</label>
              <input class="form-input w-full" x-model="clienteEdit.no_ext">
            </div>
            <div>
              <label class="text-xs text-gray-500">No int</label>
              <input class="form-input w-full" x-model="clienteEdit.no_int">
            </div>
            <div>
              <label class="text-xs text-gray-500">Colonia</label>
              <input class="form-input w-full" x-model="clienteEdit.colonia">
            </div>
            <div>
              <label class="text-xs text-gray-500">Localidad</label>
              <input class="form-input w-full" x-model="clienteEdit.localidad">
            </div>
            <div>
              <label class="text-xs text-gray-500">Estado</label>
              <input class="form-input w-full" x-model="clienteEdit.estado">
            </div>
            <div>
              <label class="text-xs text-gray-500">Código postal</label>
              <input class="form-input w-full" x-model="clienteEdit.codigo_postal">
            </div>
            <div>
              <label class="text-xs text-gray-500">País</label>
              <input class="form-input w-full" x-model="clienteEdit.pais">
            </div>
          </div>

          <div class="flex justify-end gap-2 mt-4">
            <button type="button" class="btn bg-gray-100 dark:bg-gray-700" @click="drawerClienteOpen=false">Cancelar</button>
            <button type="button" class="btn bg-violet-600 hover:bg-violet-700 text-white" @click="submitEditarCliente()">
              Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- MODAL IMPUESTOS --}}
    <div x-show="modalImpuestos.open" style="display:none" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" @click="cerrarImpuestos()"></div>

      <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl z-50 overflow-y-auto"
           @click.stop>
        <div class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">
              Impuestos del concepto #<span x-text="(modalImpuestos.idx+1)"></span>
            </h3>
            <button class="text-gray-500 hover:text-gray-700" @click="cerrarImpuestos()">✕</button>
          </div>

          <div class="space-y-2">
            <template x-for="(imp, i) in impuestosEdit" :key="imp.uid">
              <div class="border rounded-lg p-3 space-y-2">
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="text-xs text-gray-500">Tipo</label>
                    <select class="form-select w-full" x-model="imp.tipo">
                      <option value="T">Traslado</option>
                      <option value="R">Retención</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs text-gray-500">Impuesto</label>
                    <select class="form-select w-full" x-model="imp.impuesto">
                      <option value="IVA">IVA</option>
                      <option value="ISR">ISR</option>
                      <option value="IEPS">IEPS</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs text-gray-500">Factor</label>
                    <select class="form-select w-full" x-model="imp.factor">
                      <option value="Tasa">Tasa</option>
                      <option value="Cuota">Cuota</option>
                      <option value="Exento">Exento</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs text-gray-500">Tasa/Cuota (%)</label>
                    <input type="number" step="0.0001" class="form-input w-full" x-model.number="imp.tasa">
                  </div>
                </div>

                <div class="flex justify-between items-center">
                  <div class="text-xs text-gray-500">
                    Base: <span x-text="money(baseRow(form.conceptos[modalImpuestos.idx]))"></span>
                  </div>
                  <button type="button" class="btn-xs text-red-500 hover:text-red-600" @click="eliminarImpuesto(i)">Eliminar</button>
                </div>
              </div>
            </template>

            <button type="button" class="btn-sm bg-gray-100 dark:bg-gray-700 hover:opacity-90" @click="agregarImpuesto()">
              + Agregar impuesto
            </button>
          </div>

          <div class="flex justify-end gap-2 mt-4">
            <button type="button" class="btn bg-gray-100 dark:bg-gray-700" @click="cerrarImpuestos()">Cancelar</button>
            <button type="button" class="btn bg-violet-600 hover:bg-violet-700 text-white" @click="guardarImpuestos()">
              Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- MODAL SAT --}}
    <div x-show="satModal.open" style="display:none" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" @click="satModal.open=false"></div>

      <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white dark:bg-gray-900 shadow-xl z-50 overflow-y-auto"
           @click.stop>
        <div class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold" x-text="satModal.tipo==='prodserv' ? 'Buscar Clave ProdServ' : 'Buscar Clave Unidad'"></h3>
            <button class="text-gray-500 hover:text-gray-700" @click="satModal.open=false">✕</button>
          </div>

          <div class="flex gap-2 mb-3">
            <input class="form-input w-full" placeholder="Escribe al menos 3 caracteres"
                   x-model="satModal.q" @input.debounce.300ms="buscarSat()">
          </div>

          <div class="border rounded-lg divide-y">
            <template x-for="it in satModal.items" :key="it.id">
              <button type="button" class="w-full text-left p-3 hover:bg-gray-50 dark:hover:bg-gray-800"
                      @click="aplicarSat(it); satModal.open=false">
                <div class="font-medium" x-text="`${it.clave} — ${it.descripcion}`"></div>
              </button>
            </template>
            <div class="p-3 text-sm text-gray-500" x-show="satModal.items.length===0">Sin resultados</div>
          </div>

          <div class="flex justify-end mt-4">
            <button type="button" class="btn bg-gray-100 dark:bg-gray-700" @click="satModal.open=false">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
  window.facturaCreate = (opts) => ({
    opts,

    // UI flags
    isSubmitting: false,
    drawerClienteOpen: false,

    // catálogos
    metodosPago: Array.isArray(opts.metodosPago) ? opts.metodosPago : [],
    formasPago: Array.isArray(opts.formasPago) ? opts.formasPago : [],

    // datos iniciales
    clientes: Array.isArray(opts.clientes) ? opts.clientes : [],
    minFecha: opts.minFecha || '',
    maxFecha: opts.maxFecha || '',

    clienteSel: {},
    clienteEdit: {},

    // buscador de productos
    buscaProd: { query: '', suggestions: [], selectedId: '' },

    // totales
    totales: { subtotal: 0, descuento: 0, impuestos: 0, ret_local_total: 0, total: 0 },

    // modales
    modalImpuestos: { open:false, idx:-1 },
    impuestosEdit: [],
    satModal: { open:false, idx:-1, tipo:'prodserv', q:'', items:[] },

    // form
    form: {
      tipo_comprobante: 'I',
      serie: '',
      folio: '',
      fecha: opts.maxFecha || '',
      metodo_pago: 'PUE',
      forma_pago: '03',
      uso_cfdi: 'G03',
      exportacion: '01',
      complemento_exportacion: { habilitar: false, observacion: '' },

      comentarios_pdf: '',
      cliente_id: '',

      conceptos: [],
      relacionados: [],

      impuestos_locales: { ret_5_millar: false, ret_cedular_2: false },
    },

    // helpers
    uid(){ return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Math.random().toString(36).slice(2); },
    money(n){ n = Number(n||0); return n.toLocaleString('es-MX',{style:'currency',currency:'MXN'}); },

    baseRow(r){
      const sub = Number(r.cantidad||0) * Number(r.precio||0);
      const des = Number(r.descuento||0);
      return Math.max(sub - des, 0);
    },

    // calcula impuestos por fila:
    // - si hay impuestos[] usa esos
    // - si NO hay impuestos[] usa aplica_iva + iva_tasa (compat)
    impuestosRow(r){
      const base = this.baseRow(r);
      let imp = 0;

      const arr = Array.isArray(r.impuestos) ? r.impuestos : [];
      if (arr.length) {
        for (const i of arr){
          if (i.factor === 'Exento') continue;
          const tasa = Number(i.tasa||0) / 100;
          const m = base * tasa;
          imp += (i.tipo === 'R' ? -m : m);
        }
        return imp;
      }

      // compat simple
      if (r.aplica_iva) {
        const tasa = Number(r.iva_tasa ?? 0.16);
        imp += base * tasa;
      }

      return imp;
    },

    importeRow(r){
      return this.baseRow(r) + this.impuestosRow(r);
    },

    resumenImpuestos(r){
      const arr = Array.isArray(r.impuestos) ? r.impuestos : [];
      if (!arr.length) return '';
      return arr.map(i => {
        const t = (i.tipo === 'R') ? 'Ret' : 'Tras';
        const tasa = (i.factor === 'Exento') ? '0%' : (Number(i.tasa||0).toFixed(2) + '%');
        return `${t} ${i.impuesto} ${tasa}`;
      }).join(', ');
    },

    init(){
      const p = this.opts.prefill && typeof this.opts.prefill === 'object' ? this.opts.prefill : null;

      if (p && Object.keys(p).length) {
        // scalars
        for (const k of ['tipo_comprobante','serie','folio','fecha','metodo_pago','forma_pago','uso_cfdi','exportacion','comentarios_pdf','cliente_id']) {
          if (p[k] !== undefined && p[k] !== null) this.form[k] = p[k];
        }

        // objetos anidados
        if (p.complemento_exportacion && typeof p.complemento_exportacion === 'object') {
          this.form.complemento_exportacion = { ...this.form.complemento_exportacion, ...p.complemento_exportacion };
        }
        if (p.impuestos_locales && typeof p.impuestos_locales === 'object') {
          this.form.impuestos_locales = { ...this.form.impuestos_locales, ...p.impuestos_locales };
        }

        // arrays
        if (Array.isArray(p.relacionados)) {
          this.form.relacionados = p.relacionados.map(r => ({
            uid: this.uid(),
            tipo_relacion: r.tipo_relacion || '',
            uuid: r.uuid || ''
          }));
        }

        if (Array.isArray(p.conceptos)) {
          this.form.conceptos = p.conceptos.map(r => ({
            uid: this.uid(),
            descripcion: r.descripcion || '',
            clave_prod_serv: r.clave_prod_serv || '',
            clave_unidad: r.clave_unidad || '',
            unidad: r.unidad || '',
            cantidad: Number(r.cantidad || 0),
            precio: Number(r.precio || 0),
            descuento: Number(r.descuento || 0),
            impuestos: Array.isArray(r.impuestos) ? r.impuestos : [],
            aplica_iva: (r.aplica_iva !== undefined ? !!r.aplica_iva : true),
            iva_tasa: Number(r.iva_tasa ?? 0.16),
          }));
        }

        // sincroniza panel cliente
        if (this.form.cliente_id) this.onClienteChange();
      }

      // concepto inicial si sigue vacío
      if (!Array.isArray(this.form.conceptos) || !this.form.conceptos.length) {
        this.agregarConcepto();
      }

      // SOLO pedir folio si no viene del draft
      if (!this.form.serie || !this.form.folio) {
        this.pedirSiguienteFolio();
      }

      this.recalcularTotales();
    },


    async pedirSiguienteFolio(){
      try {
        const url = new URL(this.opts.endpoints.nextFolio, window.location.origin);
        // tu SeriesController soporta tipo o tipo_comprobante, le mandamos tipo
        url.searchParams.set('tipo', this.form.tipo_comprobante);

        const r = await fetch(url.toString(), { headers: { 'Accept':'application/json' } });
        const j = await r.json().catch(()=>null);

        if (!r.ok) {
          if (j && j.message) alert(j.message);
          return;
        }

        this.form.serie = j.serie ?? this.form.serie ?? '';
        this.form.folio = (j.folio != null ? String(j.folio) : (j.siguiente != null ? String(j.siguiente) : this.form.folio)) ?? '';
      } catch(e) {
        console.error(e);
      }
    },

    clampFecha(){
      if (!this.form.fecha) { this.form.fecha = this.maxFecha; return; }
      if (this.minFecha && this.form.fecha < this.minFecha) this.form.fecha = this.minFecha;
      if (this.maxFecha && this.form.fecha > this.maxFecha) this.form.fecha = this.maxFecha;
    },

    onClienteChange(){
      const id = Number(this.form.cliente_id || 0);
      const c = this.clientes.find(x => Number(x.id) === id) || {};
      this.clienteSel = c;
      this.clienteEdit = JSON.parse(JSON.stringify(c || {}));
    },

    async submitEditarCliente(){
      const id = Number(this.form.cliente_id || 0);
      if (!id) return;

      try {
        const url = `${this.opts.endpoints.clienteUpdateJsonBase}/${id}`;
        const body = new URLSearchParams();
        body.append('_token', this.opts.csrf);

        for (const [k,v] of Object.entries(this.clienteEdit || {})) {
          // manda solo valores escalares
          if (v === null || typeof v === 'undefined') body.append(k, '');
          else body.append(k, String(v));
        }

        const r = await fetch(url, {
          method: 'POST',
          headers: {
            'Accept':'application/json',
            'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body
        });

        if (!r.ok) { alert('No se pudo actualizar el cliente'); return; }

        const updated = await r.json().catch(()=>null);
        if (updated && updated.id) {
          const i = this.clientes.findIndex(x => Number(x.id) === Number(updated.id));
          if (i >= 0) this.clientes.splice(i, 1, updated);
          this.onClienteChange();
        }

        this.drawerClienteOpen = false;
      } catch(e) {
        console.error(e);
        alert('Error al actualizar cliente');
      }
    },

    agregarConcepto(){
      this.form.conceptos.push({
        uid: this.uid(),
        descripcion: '',
        clave_prod_serv: '',
        clave_unidad: '',
        unidad: '',
        cantidad: 1,
        precio: 0,
        descuento: 0,

        // impuestos avanzados (iKontrol)
        impuestos: [],

        // compat simple (tu preview viejo)
        aplica_iva: true,
        iva_tasa: 0.16,
      });
      this.recalcularTotales();
    },

    eliminarConcepto(i){
      this.form.conceptos.splice(i,1);
      this.recalcularTotales();
    },

    async buscarProductoGlobal(){
      const q = (this.buscaProd.query || '').trim();
      if (q.length < 2) { this.buscaProd.suggestions = []; return; }

      try {
        const url = new URL(this.opts.endpoints.buscarProductos, window.location.origin);
        url.searchParams.set('q', q);

        const r = await fetch(url.toString(), { headers: { 'Accept':'application/json' } });
        const list = await r.json().catch(()=>[]);
        // tu ProductosApiController regresa array directo
        this.buscaProd.suggestions = Array.isArray(list) ? list : (Array.isArray(list.data) ? list.data : []);
      } catch(e) {
        console.error(e);
      }
    },

    agregarProductoDesdeBuscador(){
      const id = Number(this.buscaProd.selectedId || 0);
      const p = (this.buscaProd.suggestions || []).find(x => Number(x.id) === id);
      if (!p) return;

      this.form.conceptos.push({
        uid: this.uid(),
        descripcion: p.descripcion || '',
        clave_prod_serv: p.clave_prod_serv || '',
        clave_unidad: p.clave_unidad || '',
        unidad: p.unidad || '',
        cantidad: 1,
        precio: Number(p.precio || 0),
        descuento: 0,
        impuestos: [],
        aplica_iva: true,
        iva_tasa: 0.16,
      });

      this.buscaProd.selectedId = '';
      this.buscaProd.query = '';
      this.buscaProd.suggestions = [];
      this.recalcularTotales();
    },

    // ===== Impuestos modal =====
    abrirImpuestos(idx){
      this.modalImpuestos.open = true;
      this.modalImpuestos.idx = idx;

      const row = this.form.conceptos[idx];
      const arr = Array.isArray(row?.impuestos) ? row.impuestos : [];
      this.impuestosEdit = arr.map(i => ({ ...i, uid: this.uid() }));

      if (!this.impuestosEdit.length) {
        // default: IVA 16 traslado (como base)
        this.impuestosEdit.push({ uid:this.uid(), tipo:'T', impuesto:'IVA', factor:'Tasa', tasa:16 });
      }
    },

    agregarImpuesto(){
      this.impuestosEdit.push({ uid:this.uid(), tipo:'T', impuesto:'IVA', factor:'Tasa', tasa:16 });
    },

    eliminarImpuesto(i){
      this.impuestosEdit.splice(i,1);
    },

    guardarImpuestos(){
      const idx = this.modalImpuestos.idx;
      if (idx < 0) return;

      for (const it of this.impuestosEdit) {
        it.tasa = Math.max(Number(it.tasa||0), 0);
      }

      this.form.conceptos[idx].impuestos = this.impuestosEdit.map(i => ({
        tipo: i.tipo || 'T',
        impuesto: i.impuesto || 'IVA',
        factor: i.factor || 'Tasa',
        tasa: Number(i.tasa||0),
      }));

      // si ya usa impuestos avanzados, el checkbox de compat pasa a “true” por coherencia
      this.form.conceptos[idx].aplica_iva = true;

      this.cerrarImpuestos();
      this.recalcularTotales();
    },

    cerrarImpuestos(){
      this.modalImpuestos.open = false;
      this.modalImpuestos.idx = -1;
      this.impuestosEdit = [];
    },

    // ===== SAT modal =====
    abrirSat(idx, tipo){
      this.satModal.open = true;
      this.satModal.idx = idx;
      this.satModal.tipo = tipo;
      this.satModal.q = '';
      this.satModal.items = [];
    },

    async buscarSat(){
      const q = (this.satModal.q || '').trim();
      if (q.length < (this.satModal.tipo === 'prodserv' ? 3 : 2)) {
        this.satModal.items = [];
        return;
      }

      try {
        const endpoint = (this.satModal.tipo === 'prodserv') ? this.opts.endpoints.satProdServ : this.opts.endpoints.satUnidad;
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('q', q);

        const r = await fetch(url.toString(), { headers: { 'Accept':'application/json' } });
        const list = await r.json().catch(()=>[]);
        this.satModal.items = Array.isArray(list) ? list : [];
      } catch(e) {
        console.error(e);
      }
    },

    aplicarSat(it){
      const idx = this.satModal.idx;
      const row = this.form.conceptos[idx];
      if (!row) return;

      if (this.satModal.tipo === 'prodserv') {
        row.clave_prod_serv = it.clave || '';
      } else {
        row.clave_unidad = it.clave || '';
        if (it.unidad) row.unidad = it.unidad;
      }

      this.recalcularTotales();
    },

    // ===== Totales =====
    recalcularTotales(){
      let subtotal = 0, descuento = 0, impuestos = 0;

      for (const r of (this.form.conceptos || [])) {
        const qty = Number(r.cantidad||0);
        const precio = Number(r.precio||0);
        subtotal += (qty * precio);
        descuento += Number(r.descuento||0);
        impuestos += this.impuestosRow(r);
      }

      const baseLocal = Math.max(subtotal - descuento, 0);
      const ret5 = this.form.impuestos_locales?.ret_5_millar ? (baseLocal * 0.005) : 0;
      const retCed = this.form.impuestos_locales?.ret_cedular_2 ? (baseLocal * 0.02) : 0;
      const retLocalTotal = ret5 + retCed;

      const total = subtotal - descuento + impuestos - retLocalTotal;

      this.totales = {
        subtotal,
        descuento,
        impuestos,
        ret5,
        retCed,
        ret_local_total: retLocalTotal,
        total: Math.max(total, 0),
      };
    },

    // ===== Relacionados =====
    agregarRelacionado(){
      this.form.relacionados.push({ uid:this.uid(), tipo_relacion:'', uuid:'' });
    },
    eliminarRelacionado(i){
      this.form.relacionados.splice(i,1);
    },

    // ===== Preview =====
    previsualizar(){
      if (!this.form.cliente_id) { alert('Selecciona un cliente'); return; }
      if (!this.form.serie || !this.form.folio) { alert('Serie/Folio inválidos'); return; }
      if (!this.form.conceptos.length) { alert('Agrega al menos un concepto'); return; }

      this.isSubmitting = true;

      // payload compatible con tu FacturasController@preview (y soporta extra fields sin romper)
      const payload = {
        rfc_activo: this.opts.rfcActivo || '',
        cliente_id: Number(this.form.cliente_id),
        tipo_comprobante: this.form.tipo_comprobante,
        
        serie: this.form.serie,
        folio: this.form.folio,
        fecha: this.form.fecha,


        metodo_pago: this.form.metodo_pago,
        forma_pago: this.form.forma_pago,
        uso_cfdi: this.form.uso_cfdi,
        exportacion: this.form.exportacion,
        complemento_exportacion: this.form.complemento_exportacion,
        moneda: 'MXN',

        comentarios_pdf: this.form.comentarios_pdf,

        impuestos_locales: this.form.impuestos_locales,
        relacionados: this.form.relacionados.map(r => ({ tipo_relacion: r.tipo_relacion, uuid: r.uuid })),

        conceptos: this.form.conceptos.map(r => ({
          descripcion: r.descripcion,
          clave_prod_serv: r.clave_prod_serv,
          clave_unidad: r.clave_unidad,
          unidad: r.unidad,
          cantidad: Number(r.cantidad||0),
          precio: Number(r.precio||0),
          descuento: Number(r.descuento||0),

          // compat simple
          aplica_iva: !!r.aplica_iva,
          iva_tasa: Number(r.iva_tasa ?? 0.16),

          // impuestos avanzados (iKontrol)
          impuestos: Array.isArray(r.impuestos) ? r.impuestos : [],
        })),
      };

      this.$refs.payload.value = JSON.stringify(payload);
      this.$nextTick(() => this.$refs.previewForm.submit());
    },
  });
</script>
@endpush
