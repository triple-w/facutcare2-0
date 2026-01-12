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
    // Fallbacks desde el servidor:
    $rfcUsuarioId = $rfcUsuarioId ?? (session('rfc_usuario_id') ?? session('rfc_activo_id') ?? 0);

    // serializa clientes con los campos que pediste
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


    // Ventana SAT: 72h hacia atrás, máximo "ahora"
    $minFecha = $minFecha ?? now()->copy()->subHours(72)->format('Y-m-d\TH:i');
    $maxFecha = $maxFecha ?? now()->format('Y-m-d\TH:i');
  @endphp

    <script>
      window.__FACTURA_CREATE_OPTS__ = {
        rfcUsuarioId: {{ (int) $rfcUsuarioId }},
        clientes: @json($clientesArr, JSON_UNESCAPED_UNICODE),
        minFecha: @json($minFecha),
        maxFecha: @json($maxFecha),
        apiSeriesNext: @json(url('/api/series/next')),
        apiProductosBuscar: @json(url('/api/productos/buscar')),
        apiSatProdServ: @json(url('/api/sat/clave-prod-serv')),
        apiSatUnidad: @json(url('/api/sat/clave-unidad')),
        routeClienteUpdateBase: @json(url('/catalogos/clientes')),
        routePreview: @json(route('facturas.preview')),
        csrf: @json(csrf_token())
      };
    </script>

  <div
    x-data="window.facturaForm ? window.facturaForm(window.__FACTURA_CREATE_OPTS__) : {}"
    class="space-y-6"
  >

    {{-- DATOS DEL COMPROBANTE --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos del comprobante</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Tipo comprobante (solo I/E) --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de comprobante</label>
          <select x-model="form.tipo_comprobante" @change="onTipoComprobanteChange" class="form-select w-full">
            <option value="I">INGRESO</option>
            <option value="E">EGRESO</option>
            <option value="T">TRASLADO</option>
            </select>
        </div>

        {{-- Serie (solo lectura, autollenada) --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Serie</label>
          <input type="text" x-model="form.serie" class="form-input w-full" readonly>
        </div>

        {{-- Folio (solo lectura, autollenado) --}}
        <div>
          <div class="flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Folio</label>
            <button type="button" class="text-xs text-violet-600 hover:text-violet-700" @click="pedirSiguienteFolio">Actualizar</button>
          </div>
          <input type="text" x-model="form.folio" class="form-input w-full" readonly>
        </div>

        {{-- Fecha (clamp 72h -> ahora) --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y hora</label>
          <input type="datetime-local" class="form-input w-full" :min="minFecha" :max="maxFecha" x-model="form.fecha" @change="clampFecha">
          <p class="text-xs text-gray-500 mt-1">La fecha debe estar dentro de las últimas 72 horas.</p>
        </div>

        {{-- Método de pago (SAT) --}}
        <select x-model="form.metodo_pago" class="form-select w-full">
        @foreach($metodosPago as $m)
            <option value="{{ is_array($m) ? $m['clave'] : $m->clave }}">
            {{ is_array($m) ? $m['clave'] : $m->clave }} — {{ is_array($m) ? $m['descripcion'] : $m->descripcion }}
            </option>
        @endforeach
        </select>

        {{-- Forma de pago (SAT) --}}
        <select x-model="form.forma_pago" class="form-select w-full">
        @foreach($formasPago as $f)
            <option value="{{ is_array($f) ? $f['clave'] : $f->clave }}">
            {{ is_array($f) ? $f['clave'] : $f->clave }} — {{ is_array($f) ? $f['descripcion'] : $f->descripcion }}
            </option>
        @endforeach
        </select>

        {{-- Uso CFDI (SAT) --}}
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




        {{-- Comentarios para PDF --}}
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios en PDF</label>
          <textarea rows="2" class="form-input w-full" x-model="form.comentarios_pdf" placeholder="Comentarios o notas visibles en el PDF"></textarea>
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
              <select x-model.number="form.cliente_id" @change="onClienteChange" class="form-select w-full">
                <option value="">— Selecciona —</option>

                <template x-for="c in clientes" :key="c.id">
                    <option :value="c.id" x-text="`${c.rfc} — ${c.razon_social}`"></option>
                </template>
                </select>
            </div>
            <button type="button" class="btn-sm bg-violet-500 hover:bg-violet-600 text-white"
                    @click="$dispatch('open-modal','modalEditarCliente')">
              Actualizar
            </button>
          </div>
        </div>
      </div>

      {{-- Datos del cliente en texto limpio --}}
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

        {{-- Buscador externo de productos (saca el buscador de la tabla) --}}
        <div class="flex items-end gap-2">
          <div>
            <label class="block text-xs text-gray-500">Buscar producto</label>
            <input type="text" class="form-input w-72" placeholder="Código o descripción"
                   x-model="buscaProd.query" @input.debounce.300ms="buscarProductoGlobal">
          </div>
          <div class="relative">
            <label class="block text-xs text-gray-500">Resultados</label>
            <select size="1" class="form-select w-72"
                    x-model.number="buscaProd.selectedId"
                    @change="agregarProductoDesdeBuscador">
            <option value="">— Selecciona —</option>

            <template x-for="p in buscaProd.suggestions" :key="p.id">
                <option :value="p.id" x-text="`${p.clave} — ${p.descripcion}`"></option>
            </template>
            </select>
          </div>
          <button type="button" class="btn-sm bg-gray-900 dark:bg-gray-700 text-white hover:opacity-90" @click="agregarConcepto">
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
              <tr class="border-b border-gray-100 dark:border-gray-700/50">
                {{-- Descripción --}}
                <td class="px-2 py-2">
                  <textarea rows="1" class="form-input w-full" x-model="row.descripcion"></textarea>
                </td>

                {{-- Clave ProdServ (editable y con selector en modal) --}}
                <td class="px-2 py-2 w-56">
                  <div class="flex gap-2 items-center">
                    <input type="text" class="form-input w-28" x-model="row.clave_prod_serv" placeholder="01010101">
                    <button type="button" class="btn-xs bg-gray-100 dark:bg-gray-700 hover:opacity-90"
                      @click="$dispatch('open-sat', { idx, tipo: 'prodserv' })">
                      Cambiar
                    </button>
                  </div>
                </td>

                {{-- Clave Unidad (editable y con selector en modal) --}}
                <td class="px-2 py-2 w-48">
                  <div class="flex gap-2 items-center">
                    <input type="text" class="form-input w-24" x-model="row.clave_unidad" placeholder="H87">
                    <button type="button" class="btn-xs bg-gray-100 dark:bg-gray-700 hover:opacity-90"
                      @click="$dispatch('open-sat', { idx, tipo: 'unidad' })">
                      Cambiar
                    </button>
                  </div>
                </td>

                {{-- Unidad --}}
                <td class="px-2 py-2 w-36">
                  <input type="text" class="form-input w-full" x-model="row.unidad" placeholder="PZA, KG, H87…">
                </td>

                {{-- Cantidad --}}
                <td class="px-2 py-2 w-24">
                  <input type="number" step="0.001" class="form-input w-full text-right" x-model.number="row.cantidad" @input="recalcularTotales">
                </td>

                {{-- Precio --}}
                <td class="px-2 py-2 w-28">
                  <input type="number" step="0.01" class="form-input w-full text-right" x-model.number="row.precio" @input="recalcularTotales">
                </td>

                {{-- Descuento --}}
                <td class="px-2 py-2 w-24">
                  <input type="number" step="0.01" class="form-input w-full text-right" x-model.number="row.descuento" @input="recalcularTotales">
                </td>

                {{-- Impuestos (modal lateral) --}}
                <td class="px-2 py-2 w-32 text-right">
                  <button type="button" class="btn-xs bg-violet-500 hover:bg-violet-600 text-white"
                          @click="$dispatch('open-impuestos', { idx })">
                    Editar
                  </button>
                  <div class="text-xs text-gray-500" x-show="(row.impuestos || []).length > 0" x-text="resumenImpuestos(row)"></div>
                </td>

                {{-- Importe --}}
                <td class="px-2 py-2 w-28 text-right" x-text="money(importeRow(row))"></td>

                {{-- eliminar --}}
                <td class="px-2 py-2 w-10 text-right">
                  <button type="button" class="btn-xs text-red-500 hover:text-red-600" @click="eliminarConcepto(idx)">✕</button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    {{-- TOTALES --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Totales</h2>
      </div>
      <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Impuestos locales</div>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input type="checkbox" class="rounded" x-model="form.impuestos_locales.ret_5_millar" @change="recalcularTotales()">
              Retención local 5 al millar (0.50%)
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mt-2">
              <input type="checkbox" class="rounded" x-model="form.impuestos_locales.ret_cedular_2" @change="recalcularTotales()">
              Retención Cedular (2.00%)
            </label>

            <p class="text-xs text-gray-500 mt-2">
              Estas retenciones se calculan sobre (subtotal - descuento) y disminuyen el total.
            </p>
          </div>
        </div>
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">Los totales se actualizan automáticamente.</div>
        <div class="w-full max-w-sm space-y-1 text-sm">
          <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span x-text="money(totales.subtotal)"></span></div>
          <div class="flex justify-between"><span class="text-gray-500">Descuento</span><span x-text="money(totales.descuento)"></span></div>
          <div class="flex justify-between"><span class="text-gray-500">Impuestos</span><span x-text="money(totales.impuestos)"></span></div>
          <div class="flex justify-between" x-show="totales.ret_local_total > 0">
            <span class="text-gray-500">Retenciones locales</span>
            <span x-text="money(totales.ret_local_total)"></span>
          </div>
          <div class="flex justify-between font-semibold text-gray-700 dark:text-gray-100"><span>Total</span><span x-text="money(totales.total)"></span></div>
        </div>
      </div>
    </div>

    {{-- DOCUMENTOS RELACIONADOS --}}
    <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Documentos relacionados</h2>
        <button type="button" class="btn-sm bg-gray-100 dark:bg-gray-700 hover:opacity-90" @click="agregarRelacionado">Agregar</button>
      </div>

      <div class="space-y-2">
        <template x-for="(rel, i) in form.relacionados" :key="rel.uid">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
              <label class="text-xs text-gray-500">Tipo relación</label>
              <select class="form-select w-full" x-model="rel.tipo_relacion">
                <option value="">—</option>
                <option value="01">01 - Nota de crédito de los documentos relacionados</option>
                <option value="02">02 - Nota de débito de los documentos relacionados</option>
                <option value="03">03 - Devolución de mercancía sobre facturas o traslados previos</option>
                <option value="04">04 - Sustitución de los CFDI previos</option>
                <option value="05">05 - Traslados de mercancías facturados previamente</option>
                <option value="06">06 - Factura generada por los traslados previos</option>
                <option value="07">07 - CFDI por aplicación de anticipo</option>
              </select>
              <p class="text-[11px] text-gray-500 mt-1">En el XML sólo se usa el valor numérico.</p>
            </div>
            <div class="md:col-span-2">
              <label class="text-xs text-gray-500">UUID</label>
              <input type="text" class="form-input w-full" x-model="rel.uuid" placeholder="UUID a relacionar">
            </div>
            <div class="flex items-end justify-end">
              <button type="button" class="btn-xs text-red-500 hover:text-red-600" @click="eliminarRelacionado(i)">Eliminar</button>
            </div>
          </div>
        </template>
      </div>
      {{-- COMPLEMENTO DE EXPORTACIÓN --}}
      <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Complemento de exportación</h3>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" class="rounded" x-model="form.complemento_exportacion.habilitar">
            Habilitar
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3" x-show="form.complemento_exportacion.habilitar">
          <div>
            <label class="text-xs text-gray-500">Exportación</label>
            <select class="form-select w-full" x-model="form.exportacion">
              <option value="01">01 - No aplica</option>
              <option value="02">02 - Definitiva</option>
              <option value="03">03 - Temporal</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Observación (opcional)</label>
            <input type="text" class="form-input w-full" x-model="form.complemento_exportacion.observacion" placeholder="(opcional)">
          </div>

          <p class="md:col-span-3 text-xs text-gray-500">
            Por ahora guardamos estos datos en el payload/borrador. El mapeo al XML lo hacemos cuando conectemos el timbrado.
          </p>
        </div>
      </div>


    </div>

    {{-- ACCIONES --}}
    <div class="flex items-center justify-end gap-3">
      <button type="button" class="btn bg-violet-600 hover:bg-violet-700 text-white" @click="previsualizar">Previsualizar</button>
    </div>

    {{-- FORMULARIO OCULTO PARA PREVIEW (abre en la misma pestaña, overlay en servidor) --}}
    <form x-ref="previewForm" action="{{ route('facturas.preview') }}" method="POST" class="hidden">
      @csrf
      <input type="hidden" name="payload" :value="JSON.stringify(form)">
    </form>

    {{-- MODAL LATERAL: EDITAR CLIENTE --}}
    <div x-data="{open:false}"
        x-on:open-modal.window="if($event.detail==='modalEditarCliente') open=true"
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40"
        style="display:none">

    {{-- Overlay --}}
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>

    {{-- Panel (derecha) --}}
    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl
                z-50 overflow-y-auto"
        @click.stop
        x-transition:enter="transform transition ease-in-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @keydown.escape.window="open=false">

        <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Editar cliente</h3>
            <button class="text-gray-500 hover:text-gray-700" @click="open=false">✕</button>
        </div>

        <template x-if="clienteSel && form.cliente_id">
            <form @submit.prevent="submitEditarCliente">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                <label class="text-xs text-gray-500">Razón social</label>
                <input class="form-input w-full" x-model="clienteEdit.razon_social">
                </div>
                <div>
                <label class="text-xs text-gray-500">RFC</label>
                <input class="form-input w-full" x-model="clienteEdit.rfc">
                </div>
                <div class="sm:col-span-2">
                <label class="text-xs text-gray-500">Correo</label>
                <input type="email" class="form-input w-full" x-model="clienteEdit.email">
                </div>
                <div><label class="text-xs text-gray-500">Calle</label><input class="form-input w-full" x-model="clienteEdit.calle"></div>
                <div><label class="text-xs text-gray-500">No. ext</label><input class="form-input w-full" x-model="clienteEdit.no_ext"></div>
                <div><label class="text-xs text-gray-500">No. int</label><input class="form-input w-full" x-model="clienteEdit.no_int"></div>
                <div><label class="text-xs text-gray-500">Colonia</label><input class="form-input w-full" x-model="clienteEdit.colonia"></div>
                <div><label class="text-xs text-gray-500">Localidad</label><input class="form-input w-full" x-model="clienteEdit.localidad"></div>
                <div><label class="text-xs text-gray-500">Estado</label><input class="form-input w-full" x-model="clienteEdit.estado"></div>
                <div><label class="text-xs text-gray-500">CP</label><input class="form-input w-full" x-model="clienteEdit.codigo_postal"></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">País</label><input class="form-input w-full" x-model="clienteEdit.pais"></div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" class="btn bg-gray-100 dark:bg-gray-700" @click="open=false">Cancelar</button>
                <button type="submit" class="btn bg-violet-600 hover:bg-violet-700 text-white">Guardar</button>
            </div>
            </form>
        </template>

        <template x-if="!form.cliente_id">
            <div class="text-sm text-gray-500">Primero selecciona un cliente.</div>
        </template>
        </div>
    </div>
    </div>


    {{-- MODAL LATERAL: IMPUESTOS POR CONCEPTO --}}
    <div x-data="{open:false}"
        x-on:open-impuestos.window="open=true; modalImpuestos.idx = $event.detail.idx; cargarImpuestosEdit()"
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40"
        style="display:none">

    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>

    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl
                z-50 overflow-y-auto"
        @click.stop
        x-transition:enter="transform transition ease-in-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @keydown.escape.window="open=false">

        <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">
            Impuestos del concepto #<span x-text="(modalImpuestos.idx+1)"></span>
            </h3>
            <button class="text-gray-500 hover:text-gray-700" @click="open=false">✕</button>
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

            <button type="button" class="btn-sm bg-gray-100 dark:bg-gray-700 hover:opacity-90" @click="agregarImpuesto">+ Agregar impuesto</button>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <button type="button" class="btn bg-gray-100 dark:bg-gray-700" @click="open=false">Cancelar</button>
            <button type="button" class="btn bg-violet-600 hover:bg-violet-700 text-white" @click="guardarImpuestos(); open=false">Guardar</button>
        </div>
        </div>
    </div>
    </div>


    {{-- MODAL LATERAL: SELECTOR de CLAVES SAT --}}
    <div x-data="{open:false}"
        x-on:open-sat.window="open=true; satModal.idx = $event.detail.idx; satModal.tipo = $event.detail.tipo; satModal.q=''; satModal.items=[];"
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40"
        style="display:none">

    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>

    <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white dark:bg-gray-900 shadow-xl
                z-50 overflow-y-auto"
        @click.stop
        x-transition:enter="transform transition ease-in-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @keydown.escape.window="open=false">

        <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold" x-text="satModal.tipo==='prodserv' ? 'Buscar Clave ProdServ' : 'Buscar Clave Unidad'"></h3>
            <button class="text-gray-500 hover:text-gray-700" @click="open=false">✕</button>
        </div>

        <div class="flex gap-2 mb-3">
            <input class="form-input w-full" placeholder="Escribe al menos 3 caracteres" x-model="satModal.q"
                @input.debounce.300ms="buscarSat()">
        </div>

        <div class="border rounded-lg divide-y">
            <template x-for="it in satModal.items" :key="it.id">
            <button type="button" class="w-full text-left p-3 hover:bg-gray-50 dark:hover:bg-gray-800"
                    @click="aplicarSat(it); open=false">
                <div class="font-medium" x-text="`${it.clave} — ${it.descripcion}`"></div>
            </button>
            </template>
            <div class="p-3 text-sm text-gray-500" x-show="satModal.items.length===0">Sin resultados</div>
        </div>

        <div class="flex justify-end mt-4">
            <button type="button" class="btn bg-gray-100 dark:bg-gray-700" @click="open=false">Cerrar</button>
        </div>
        </div>
    </div>
    </div>


  </div> {{-- x-data --}}
</div>
@endsection

@push('scripts')
<script>
  window.facturaForm = (opts) => ({
    // ----- estado -----
    form: {
      tipo_comprobante: 'I', // default
      serie: '',
      folio: '',
      fecha: opts.maxFecha, // por defecto "ahora"
      metodo_pago: 'PUE',
      forma_pago: '03',     // Transferencia por default
      uso_cfdi: 'G03', // default práctico
      comentarios_pdf: '',
      cliente_id: '',
      conceptos: [],
      relacionados: [],

       // Exportación
      exportacion: '01',
      complemento_exportacion: {
        habilitar: false,
        observacion: '',
    },

    impuestos_locales: {
      ret_5_millar: false,
      ret_cedular_2: false,
    },

    clientes: opts.clientes || [],
    minFecha: opts.minFecha,
    maxFecha: opts.maxFecha,
    clienteSel: {},
    clienteEdit: {},

    buscaProd: { query: '', suggestions: [], selectedId: '' },

    totales: { subtotal: 0, descuento: 0, impuestos: 0, total: 0 },

    modalImpuestos: { open:false, idx:-1 },
    impuestosEdit: [],
    satModal: { open:false, idx:-1, tipo:'prodserv', q:'', items:[] },

    // ----- helpers -----
    uid(){ return Math.random().toString(36).slice(2); },
    money(n){ n = Number(n||0); return n.toLocaleString('es-MX',{style:'currency',currency:'MXN'}); },

    baseRow(r){
      const sub = Number(r.cantidad||0) * Number(r.precio||0);
      const des = Number(r.descuento||0);
      return Math.max(sub - des, 0);
    },
    importeRow(r){
      const base = this.baseRow(r);
      let imp = 0;
      for (const i of (r.impuestos||[])) {
        if (i.factor==='Exento') continue;
        const tasa = Number(i.tasa||0)/100;
        const monto = base * tasa;
        imp += (i.tipo==='R' ? -monto : monto);
      }
      return base + imp;
    },
    resumenImpuestos(r){
      const xs = (r.impuestos||[]).map(i => `${i.tipo==='R'?'Ret':'Tras'} ${i.impuesto} ${i.factor==='Exento'?'0%':(Number(i.tasa||0).toFixed(2)+'%')}`);
      return xs.join(', ');
    },

    // ----- init -----
    // ----- init -----
    init(){
      // ¿Tenemos payload de regreso desde PREVIEW?
      const restore = window.__RESTORE_FACTURA__ || null;

      if (restore && typeof restore === 'object') {
        // Campos principales
        if (restore.tipo_comprobante) this.form.tipo_comprobante = restore.tipo_comprobante;
        if (typeof restore.serie !== 'undefined') this.form.serie = restore.serie || '';
        if (typeof restore.folio !== 'undefined') this.form.folio = String(restore.folio ?? '');
        if (restore.fecha) this.form.fecha = restore.fecha;
        if (restore.metodo_pago) this.form.metodo_pago = restore.metodo_pago;
        if (restore.forma_pago) this.form.forma_pago = restore.forma_pago;
        if (typeof restore.comentarios_pdf !== 'undefined') this.form.comentarios_pdf = restore.comentarios_pdf || '';

        if (typeof restore.uso_cfdi !== 'undefined') this.form.uso_cfdi = restore.uso_cfdi || 'G03';

        // Exportación
        if (typeof restore.exportacion !== 'undefined') this.form.exportacion = restore.exportacion || '01';
        if (restore.complemento_exportacion && typeof restore.complemento_exportacion === 'object') {
          this.form.complemento_exportacion.habilitar = !!restore.complemento_exportacion.habilitar;
          this.form.complemento_exportacion.observacion = restore.complemento_exportacion.observacion || '';
        }

        // Impuestos locales
        if (restore.impuestos_locales && typeof restore.impuestos_locales === 'object') {
          this.form.impuestos_locales.ret_5_millar = !!restore.impuestos_locales.ret_5_millar;
          this.form.impuestos_locales.ret_cedular_2 = !!restore.impuestos_locales.ret_cedular_2;
        }



        // Cliente
        if (restore.cliente_id) {
          this.form.cliente_id = Number(restore.cliente_id);
          // Sincroniza derivados (clienteSel, clienteEdit)
          this.onClienteChange();
        }

        // Conceptos
        if (Array.isArray(restore.conceptos) && restore.conceptos.length) {
          // Clona profundo y asegura estructura esperada
          this.form.conceptos = restore.conceptos.map(c => {
            const r = {
              uid: this.uid(),
              descripcion: c.descripcion || '',
              clave_prod_serv: c.clave_prod_serv || '',
              clave_unidad: c.clave_unidad || '',
              unidad: c.unidad || '',
              cantidad: Number(c.cantidad || 0),
              precio: Number(c.precio || 0),
              descuento: Number(c.descuento || 0),
              impuestos: Array.isArray(c.impuestos) ? c.impuestos.map(i => ({
                tipo: i.tipo || 'T',     // T=Traslado, R=Retención
                impuesto: i.impuesto || 'IVA',
                factor: i.factor || 'Tasa', // Tasa/Exento
                tasa: Number(i.tasa || 0),  // porcentaje ej. 16
              })) : [],
            };
            return r;
          });
          // Recalcula totales con los conceptos restaurados
          this.recalcularTotales();
        } else {
          // Si NO venían conceptos, deja uno por defecto como antes
          this.agregarConcepto();
        }

        // Relacionados
        if (Array.isArray(restore.relacionados)) {
          // Cada elemento esperado: { tipo_relacion: '01..07', uuid: '...' }
          this.form.relacionados = restore.relacionados.map(r => ({
            tipo_relacion: r.tipo_relacion || '',
            uuid: r.uuid || '',
          }));
        }

        // Si NO vino serie/folio, pide el siguiente automáticamente:
        if (!this.form.serie || !this.form.folio) {
          this.pedirSiguienteFolio();
        }
      } else {
        // Flujo normal (cuando NO vienes del preview)
        this.pedirSiguienteFolio();
        this.agregarConcepto();
      }
    },


    // ----- comprobante -----
    onTipoComprobanteChange(){ this.pedirSiguienteFolio(); },
    pedirSiguienteFolio(){
    const url = `${opts.apiSeriesNext}?tipo=${encodeURIComponent(this.form.tipo_comprobante)}`;
    fetch(url)
        .then(r=>r.json())
        .then(j=>{
        this.form.serie = j.serie || '';
        this.form.folio = (j.folio != null ? j.folio : j.siguiente) || '';
        })
        .catch(()=>{});
    },
    clampFecha(){
      if (!this.form.fecha) { this.form.fecha = this.maxFecha; return; }
      if (this.form.fecha < this.minFecha) this.form.fecha = this.minFecha;
      if (this.form.fecha > this.maxFecha) this.form.fecha = this.maxFecha;
    },

    // ----- cliente -----
    onClienteChange(){
      const c = this.clientes.find(x => Number(x.id) === Number(this.form.cliente_id));
      this.clienteSel = c || {};
      this.clienteEdit = JSON.parse(JSON.stringify(this.clienteSel || {}));
    },
    async submitEditarCliente(){
      if (!this.form.cliente_id) return;
      const url = `${opts.routeClienteUpdateBase}/${this.form.cliente_id}`;
      const body = new URLSearchParams();
      body.append('_token', opts.csrf);
      body.append('_method','PUT');
      for (const [k,v] of Object.entries(this.clienteEdit)) body.append(k, v ?? '');
      const r = await fetch(url, {
        method:'POST',
        headers:{
          'Accept':'application/json',
          'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body
      });

      if (!r.ok) { alert('No se pudo actualizar el cliente'); return; }
      const j = await r.json().catch(()=>null);
      if (j && j.id){
        // actualiza en la lista local
        const i = this.clientes.findIndex(x => Number(x.id)===Number(j.id));
        if (i>=0) this.clientes.splice(i,1,j);
        this.onClienteChange();
      } else {
        // si el controlador respondió redirect/html, al menos refrescamos la UI local
        this.onClienteChange();
      }
      // cierra drawer
      document.querySelector('[x-ref=drawerCliente]')?.classList.add('hidden');
    },

    // ----- conceptos -----
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
        impuestos: [],
      });
      this.recalcularTotales();
    },
    eliminarConcepto(i){
      this.form.conceptos.splice(i,1);
      this.recalcularTotales();
    },

    // buscador externo
    async buscarProductoGlobal(){
      const q = (this.buscaProd.query||'').trim();
      if (q.length < 2) { this.buscaProd.suggestions = []; return; }
      const url = `${opts.apiProductosBuscar}?q=${encodeURIComponent(q)}&rfc=${encodeURIComponent(opts.rfcUsuarioId)}`;
      const r = await fetch(url);
      const list = await r.json().catch(()=>[]);
      this.buscaProd.suggestions = list || [];
    },
    agregarProductoDesdeBuscador(){
      const id = Number(this.buscaProd.selectedId||0);
      const p = (this.buscaProd.suggestions||[]).find(x => Number(x.id)===id);
      if (!p) return;
      const row = {
        uid: this.uid(),
        descripcion: p.descripcion || '',
        clave_prod_serv: p.clave_prod_serv || '',
        clave_unidad: p.clave_unidad || '',
        unidad: p.unidad || '',
        cantidad: 1,
        precio: Number(p.precio || 0),
        descuento: 0,
        impuestos: [],
      };
      this.form.conceptos.push(row);
      this.buscaProd.selectedId = '';
      this.recalcularTotales();
    },

    // impuestos modal
    cargarImpuestosEdit(){
      const idx = this.modalImpuestos.idx;
      const row = this.form.conceptos[idx]; if (!row) return;
      this.impuestosEdit = (row.impuestos || []).map(i => ({...i})) // clone
      if (!this.impuestosEdit.length) this.agregarImpuesto();
    },
    agregarImpuesto(){
      this.impuestosEdit.push({ uid:this.uid(), tipo:'T', impuesto:'IVA', factor:'Tasa', tasa:16 });
    },
    eliminarImpuesto(i){ this.impuestosEdit.splice(i,1); },
    guardarImpuestos(){
      const idx = this.modalImpuestos.idx;
      if (idx<0) return;
      // limpieza simple: tasa >=0
      for (const it of this.impuestosEdit){ it.tasa = Math.max(Number(it.tasa||0), 0); }
      this.form.conceptos[idx].impuestos = this.impuestosEdit.map(i => ({...i}));
      this.cerrarImpuestos();
      this.recalcularTotales();
    },
    cerrarImpuestos(){ this.modalImpuestos.open=false; this.modalImpuestos.idx=-1; this.impuestosEdit = []; },

    // SAT modal
    async buscarSat(){
      const q = (this.satModal.q||'').trim();
      if (q.length < 3) { this.satModal.items = []; return; }
      const isProd = this.satModal.tipo === 'prodserv';
      const url = (isProd ? opts.apiSatProdServ : opts.apiSatUnidad) + `?q=${encodeURIComponent(q)}`;
      const r = await fetch(url);
      const list = await r.json().catch(()=>[]);
      this.satModal.items = list || [];
    },
    aplicarSat(it){
      const idx = this.satModal.idx;
      const row = this.form.conceptos[idx]; if (!row) return;
      if (this.satModal.tipo==='prodserv') row.clave_prod_serv = it.clave || '';
      else if (this.satModal.tipo==='unidad'){ row.clave_unidad = it.clave || ''; row.unidad = it.unidad || row.unidad; }
      this.satModal.open=false;
    },

    // totales
    recalcularTotales(){
      let subtotal=0, descuento=0, impuestos=0;

      for (const r of this.form.conceptos){
        const base = this.baseRow(r);
        subtotal += Number(r.cantidad||0) * Number(r.precio||0);
        descuento += Number(r.descuento||0);

        for (const i of (r.impuestos||[])){
          if (i.factor==='Exento') continue;
          const tasa = Number(i.tasa||0)/100;
          const m = base * tasa;
          impuestos += (i.tipo==='R' ? -m : m);
        }
      }

      const baseLocal = Math.max(subtotal - descuento, 0);

      const ret5 = this.form.impuestos_locales?.ret_5_millar ? (baseLocal * 0.005) : 0;
      const retCed = this.form.impuestos_locales?.ret_cedular_2 ? (baseLocal * 0.02) : 0;

      const retLocalTotal = ret5 + retCed;

      const total = subtotal - descuento + impuestos - retLocalTotal;

      this.totales = { subtotal, descuento, impuestos, ret5, retCed, ret_local_total: retLocalTotal, total };
    },


    // relacionados
    agregarRelacionado(){ this.form.relacionados.push({ uid:this.uid(), tipo_relacion:'', uuid:'' }); },
    eliminarRelacionado(i){ this.form.relacionados.splice(i,1); },

    // acciones
    previsualizar(){
      // validación mínima en cliente
      if (!this.form.cliente_id) { alert('Selecciona un cliente'); return; }
      if (!this.form.serie || !this.form.folio) { alert('Serie/Folio inválidos'); return; }
      if (!this.form.conceptos.length) { alert('Agrega al menos un concepto'); return; }
      // post al preview (servidor hará validación completa)
      this.$refs.previewForm.submit();
    },
    guardarBorrador(){
      alert('El guardado definitivo se hará desde la Previsualización, para obligar la validación previa.');
      this.previsualizar();
    },
  });

</script>
@endpush
