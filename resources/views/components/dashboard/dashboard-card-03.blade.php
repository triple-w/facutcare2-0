<div class="flex flex-col col-span-full sm:col-span-6 xl:col-span-4 bg-white dark:bg-gray-800 shadow-xs rounded-xl">
    <div class="px-5 pt-5">
        @php
            $k = $kpis['egresos'] ?? ['actual'=>0,'previo'=>0,'delta_pct'=>null,'top_cliente'=>['nombre'=>'','total'=>0]];
            $delta = $k['delta_pct'];
            $deltaText = $delta === null ? '-' : ((float)$delta >= 0 ? '+' : '') . number_format((float)$delta, 1) . '%';
            $deltaClass = $delta === null ? 'text-gray-700 bg-gray-500/20' : ((float)$delta >= 0 ? 'text-green-700 bg-green-500/20' : 'text-red-700 bg-red-500/20');
            $topNombre = (string)($k['top_cliente']['nombre'] ?? '');
            $topTotal = (float)($k['top_cliente']['total'] ?? 0);
        @endphp
        <header class="flex justify-between items-start mb-2">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Egresos</h2>
        </header>
        <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase mb-1">Total</div>
        <div class="flex items-start">
            <div class="text-3xl font-bold text-gray-800 dark:text-gray-100 mr-2">${{ number_format((float)$k['actual'], 2) }}</div>
            <div class="text-sm font-medium px-1.5 rounded-full {{ $deltaClass }}">{{ $deltaText }}</div>
        </div>
        <div class="text-xs text-gray-500 mt-2">
            Año pasado: ${{ number_format((float)$k['previo'], 2) }}
        </div>
        <div class="text-xs text-gray-500 mt-1">
            @if($topNombre !== '')
                Top cliente: {{ $topNombre }} ({{ number_format($topTotal, 2) }})
            @else
                Top cliente: -
            @endif
        </div>
    </div>
    <!-- Chart built with Chart.js 3 -->
    <!-- Check out src/js/components/dashboard-card-03.js for config -->
    <div class="grow max-sm:max-h-[128px] xl:max-h-[128px]">
        <!-- Change the height attribute to adjust the chart height -->
        <canvas id="dashboard-card-03" width="389" height="128"></canvas>
    </div>
</div>
