<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas</h2>
            <a href="{{ route('facturas.nueva') }}" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">
                + Nueva factura
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('ok'))
                <div class="p-3 rounded bg-green-50 text-green-700 text-sm">{{ session('ok') }}</div>
            @endif
            @if(session('error'))
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
            @endif

            {{-- 🔎 Buscador instantáneo (client-side) --}}
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
                            (página <span id="pageNow" class="font-semibold">1</span> /
                            <span id="pageLast" class="font-semibold">1</span>)
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                id="btnPrev"
                                type="button"
                                class="px-3 py-1 rounded-md bg-gray-100 disabled:opacity-50"
                            >
                                ← Anterior 300
                            </button>

                            <button
                                id="btnNext"
                                type="button"
                                class="px-3 py-1 rounded-md bg-gray-100 disabled:opacity-50"
                            >
                                Siguiente 300 →
                            </button>
                        </div>
                    </div>

                    <div id="loadingBar" class="hidden text-xs text-gray-500">
                        Cargando página…
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

                        <tbody id="facturasTbody" class="divide-y">
                            @include('facturas.partials.rows', ['facturas' => $facturas])
                        </tbody>
                    </table>
                </div>

                {{-- ❌ Ya no usamos $facturas->links() aquí (porque paginamos con botones de 300 + AJAX) --}}
            </div>

            {{-- Si tú ya tenías modal de cancelar, lo dejamos tal cual estuviera en tu archivo.
                 En tu index actual sí existe y al final tienes scripts + $facturas->links(). 
                 Si quieres conservar el modal, pégalo aquí abajo exactamente como lo tenías. --}}
        </div>
    </div>

    <script>
        (function () {
            const endpoint = @json(route('facturas.index'));

            const input   = document.getElementById('quickFilter');
            const btnClear= document.getElementById('btnClear');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');

            const tbody   = document.getElementById('facturasTbody');
            const loading = document.getElementById('loadingBar');

            const shownCount = document.getElementById('shownCount');
            const pageCount  = document.getElementById('pageCount');
            const pageNow    = document.getElementById('pageNow');
            const pageLast   = document.getElementById('pageLast');

            // Estado inicial (viene del paginator)
            let currentPage = Number(@json($facturas->currentPage()));
            let lastPageVal = Number(@json($facturas->lastPage()));
            let pageRowCount= Number(@json($facturas->count()));
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
                    alert('No pude cargar la página. Revisa consola/logs.');
                } finally {
                    loading.classList.add('hidden');
                    updatePagerUi();
                }
            }

            // Eventos
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

            // Init
            updatePagerUi();
            currentFilter = '';
            applyFilter();
        })();
    </script>
</x-app-layout>
