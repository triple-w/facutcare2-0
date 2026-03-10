<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        @php
            $factucareDashboardPayload = [
                'kpis' => $kpis ?? [],
                'monthlyChart' => $monthlyChart ?? [
                    'labels' => [],
                    'facturas' => [],
                    'complementos' => [],
                    'notas_credito' => [],
                ],
            ];
        @endphp

        <script>
            window.factucareDashboard = @json($factucareDashboardPayload);
        </script>

        <!-- Dashboard actions -->
        <div class="sm:flex sm:justify-between sm:items-center mb-8">

            <!-- Left: Title -->
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Dashboard</h1>
            </div>

            <!-- Right: Actions -->
            <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">

                <!-- Filter button -->
                <x-dropdown-filter align="right" />

                <form method="GET" action="{{ route('dashboard') }}">
                    <select name="range"
                            class="form-select dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium"
                            onchange="this.form.submit()">
                        <option value="month" @selected(($range ?? 'month') === 'month')>Este mes</option>
                        <option value="3m" @selected(($range ?? '') === '3m')>Ultimos 3 meses</option>
                        <option value="6m" @selected(($range ?? '') === '6m')>Ultimo semestre</option>
                        <option value="12m" @selected(($range ?? '') === '12m')>Ultimo año</option>
                    </select>
                </form>
            </div>

        </div>
        
        <!-- Cards -->
        <div class="grid grid-cols-12 gap-6">

            <x-dashboard.dashboard-card-01 :kpis="$kpis" />
            <x-dashboard.dashboard-card-02 :kpis="$kpis" />
            <x-dashboard.dashboard-card-03 :kpis="$kpis" />
            <x-dashboard.dashboard-card-14 :cards="$documentCards" />
            <x-dashboard.dashboard-card-15 :monthlyChart="$monthlyChart" />

        </div>

    </div>
</x-app-layout>
