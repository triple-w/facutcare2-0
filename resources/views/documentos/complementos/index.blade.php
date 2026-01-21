<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Complementos de pago</h2>
            <a href="{{ route('complementos.create') }}" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">
                + Nuevo complemento
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="p-3 rounded bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-4">
                <div class="flex flex-col gap-2">
                    <div class="flex gap-2 items-center">
                        <input
                            id="quickFilter"
                            class="w-full rounded-md border-gray-300"
                            placeholder="Buscar en esta tabla (serie, folio, cliente, RFC, UUID, estatus, total...)"
                            autocomplete="off"
                        />
                        <button
                            id="btnClear"
                            type="button"
                            class="px-4 py-2 bg-gray-100 rounded-md"
                        >
                            Limpiar
                        </button>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <div>
                            Mostrando <span id="shownCount" class="font-semibold">0</span> de
                            <span id="pageCount" class="font-semibold">0</span>
                            (pagina <span id="pageNow" class="font-semibold">1</span> /
                            <span id="pageLast" class="font-semibold">1</span>)
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                id="btnPrev"
                                type="button"
                                class="px-3 py-1 rounded-md bg-gray-100 disabled:opacity-50"
                            >
                                ‹ Anterior
                            </button>

                            <button
                                id="btnNext"
                                type="button"
                                class="px-3 py-1 rounded-md bg-gray-100 disabled:opacity-50"
                            >
                                Siguiente ›
                            </button>
                        </div>
                    </div>
                    <div id="loadingBar" class="hidden text-xs text-gray-500">
                        Cargando pagina...
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Serie / Folio</th>
                                <th class="px-4 py-3 text-left">Cliente</th>
                                <th class="px-4 py-3 text-left">Tipo de documento</th>
                                <th class="px-4 py-3 text-left">Estatus</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="complementosTbody" class="divide-y">
                            @include('documentos.complementos.partials.rows', ['rows' => $rows])
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="cancelModal"
                 class="fixed inset-0 z-50 hidden"
                 aria-hidden="true">
                <div class="absolute inset-0 bg-black/40"></div>

                <div class="relative min-h-full flex items-center justify-center p-4">
                    <div class="w-full max-w-lg bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="px-5 py-4 border-b flex items-center justify-between">
                            <div>
                                <div class="text-lg font-semibold">Cancelar complemento</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    UUID: <span id="cancelComplementoUuid" class="font-mono">—</span>
                                </div>
                            </div>
                            <button type="button"
                                    id="btnCancelCloseX"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-md hover:bg-gray-100">
                                ×
                            </button>
                        </div>

                        <form id="cancelForm" method="POST" action="">
                            @csrf

                            <input type="hidden" name="complemento_id" id="cancelComplementoId" value="">
                            <input type="hidden" name="complemento_uuid" id="cancelComplementoUuidHidden" value="">

                            <div class="px-5 py-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Motivo de cancelacion (SAT)
                                    </label>
                                    <select name="motivo"
                                            id="cancelMotivo"
                                            class="w-full rounded-md border-gray-300">
                                        <option value="">— Selecciona —</option>
                                        <option value="01">01 - Comprobantes emitidos con errores con relacion</option>
                                        <option value="02">02 - Comprobantes emitidos con errores sin relacion</option>
                                        <option value="03">03 - No se llevo a cabo la operacion</option>
                                        <option value="04">04 - Operacion nominativa relacionada en una factura global</option>
                                    </select>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Nota: el motivo <b>01</b> normalmente requiere UUID de sustitucion.
                                    </div>
                                </div>

                                <div id="wrapUuidSustitucion" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        UUID de sustitucion
                                    </label>
                                    <input type="text"
                                           name="foliosustitucion"
                                           id="cancelUuidSustitucion"
                                           class="w-full rounded-md border-gray-300 font-mono"
                                           placeholder="Ej: 550e8400-e29b-41d4-a716-446655440000">
                                    <div class="text-xs text-gray-500 mt-1">
                                        Solo aplica cuando el motivo es <b>01</b>.
                                    </div>
                                </div>

                                <div class="text-xs text-gray-500">
                                    Esto enviara la solicitud al PAC con el motivo seleccionado.
                                </div>
                            </div>

                            <div class="px-5 py-4 border-t flex items-center justify-end gap-2 bg-gray-50">
                                <button type="button"
                                        id="btnCancelClose"
                                        class="px-4 py-2 rounded-md bg-white border hover:bg-gray-100">
                                    Cerrar
                                </button>

                                <button type="submit"
                                        class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">
                                    Confirmar cancelacion
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        (function () {
            const endpoint = @json(route('complementos.index'));

            const input   = document.getElementById('quickFilter');
            const btnClear= document.getElementById('btnClear');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');

            const tbody   = document.getElementById('complementosTbody');
            const loading = document.getElementById('loadingBar');

            const shownCount = document.getElementById('shownCount');
            const pageCount  = document.getElementById('pageCount');
            const pageNow    = document.getElementById('pageNow');
            const pageLast   = document.getElementById('pageLast');

            let currentPage = Number(@json($rows->currentPage()));
            let lastPageVal = Number(@json($rows->lastPage()));
            let pageRowCount= Number(@json($rows->count()));
            let currentFilter = '';

            function normalize(s) {
                return (s || '').toString().trim().toLowerCase();
            }

            function updatePagerUi() {
                pageNow.textContent  = String(currentPage);
                pageLast.textContent = String(lastPageVal);
                pageCount.textContent= String(pageRowCount);

                btnPrev.disabled = (currentPage <= 1);
                btnNext.disabled = (currentPage >= lastPageVal);
            }

            function applyFilter() {
                const q = normalize(currentFilter);
                const rows = tbody.querySelectorAll('tr[data-search]');
                let visible = 0;

                rows.forEach(tr => {
                    const hay = tr.getAttribute('data-search') || '';
                    const show = (q === '') ? true : hay.includes(q);
                    tr.classList.toggle('hidden', !show);
                    if (show) visible++;
                });

                shownCount.textContent = String(visible);
            }

            async function loadPage(page) {
                if (page < 1 || page > lastPageVal) return;

                loading.classList.remove('hidden');
                btnPrev.disabled = true;
                btnNext.disabled = true;

                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('page', String(page));

                    const res = await fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }

                    const data = await res.json();

                    tbody.innerHTML = data.rows_html || '';

                    const meta = data.meta || {};
                    currentPage  = Number(meta.current_page || page);
                    lastPageVal  = Number(meta.last_page || lastPageVal);
                    pageRowCount = Number(meta.count || 0);

                    updatePagerUi();
                    applyFilter();
                } catch (e) {
                    console.error(e);
                    alert('No pude cargar la pagina. Revisa consola/logs.');
                } finally {
                    loading.classList.add('hidden');
                    updatePagerUi();
                }
            }

            const cancelModal = document.getElementById('cancelModal');
            const cancelForm  = document.getElementById('cancelForm');

            const cancelComplementoId = document.getElementById('cancelComplementoId');
            const cancelComplementoUuid = document.getElementById('cancelComplementoUuid');
            const cancelComplementoUuidHidden = document.getElementById('cancelComplementoUuidHidden');

            const cancelMotivo = document.getElementById('cancelMotivo');
            const wrapUuidSust = document.getElementById('wrapUuidSustitucion');
            const cancelUuidSust = document.getElementById('cancelUuidSustitucion');

            const btnCancelClose = document.getElementById('btnCancelClose');
            const btnCancelCloseX = document.getElementById('btnCancelCloseX');

            const cancelBase = @json(url('/documentos/complementos'));

            function openCancelModal({ id, uuid }) {
                cancelComplementoId.value = id || '';
                cancelComplementoUuid.textContent = uuid || '—';
                cancelComplementoUuidHidden.value = uuid || '';

                cancelMotivo.value = '';
                cancelUuidSust.value = '';
                wrapUuidSust.classList.add('hidden');

                cancelForm.action = cancelBase + '/' + encodeURIComponent(id) + '/cancelar';

                cancelModal.classList.remove('hidden');
                cancelModal.setAttribute('aria-hidden', 'false');
            }

            function closeCancelModal() {
                cancelModal.classList.add('hidden');
                cancelModal.setAttribute('aria-hidden', 'true');
            }

            cancelMotivo.addEventListener('change', function () {
                const v = (cancelMotivo.value || '').trim();
                if (v === '01') {
                    wrapUuidSust.classList.remove('hidden');
                    cancelUuidSust.setAttribute('required', 'required');
                } else {
                    wrapUuidSust.classList.add('hidden');
                    cancelUuidSust.removeAttribute('required');
                    cancelUuidSust.value = '';
                }
            });

            btnCancelClose.addEventListener('click', closeCancelModal);
            btnCancelCloseX.addEventListener('click', closeCancelModal);

            cancelModal.addEventListener('click', function (e) {
                if (e.target === cancelModal || e.target.classList.contains('bg-black/40')) {
                    closeCancelModal();
                }
            });

            tbody.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-action="open-cancel-modal"]');
                if (!btn) return;

                e.preventDefault();

                const id = btn.getAttribute('data-id');
                const uuid = btn.getAttribute('data-uuid');
                if (!id) return;

                openCancelModal({ id, uuid });
            });

            input.addEventListener('input', function () {
                currentFilter = input.value;
                applyFilter();
            });

            btnClear.addEventListener('click', function () {
                input.value = '';
                currentFilter = '';
                applyFilter();
                input.focus();
            });

            btnPrev.addEventListener('click', function () {
                loadPage(currentPage - 1);
            });

            btnNext.addEventListener('click', function () {
                loadPage(currentPage + 1);
            });

            updatePagerUi();
            currentFilter = '';
            applyFilter();
        })();
    </script>
</x-app-layout>
