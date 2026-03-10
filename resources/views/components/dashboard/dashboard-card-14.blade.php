<div class="col-span-full xl:col-span-5 bg-white dark:bg-gray-800 shadow-xs rounded-xl">
    <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Documentos</h2>
    </header>
    <div class="p-3">
        <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
            @foreach (($cards ?? []) as $card)
                @php
                    $tone = $card['tone'] ?? 'gray';
                    $iconClass = match($tone) {
                        'violet' => 'bg-violet-500 text-white',
                        'sky' => 'bg-sky-500 text-white',
                        'amber' => 'bg-amber-500 text-white',
                        'red' => 'bg-red-500 text-white',
                        default => 'bg-gray-200 text-gray-700',
                    };
                @endphp
                <li class="flex items-center gap-3 px-2 py-3">
                    <div class="w-10 h-10 rounded-full shrink-0 flex items-center justify-center {{ $iconClass }}">
                        <span class="text-sm font-bold">{{ (int) ($card['count'] ?? 0) }}</span>
                    </div>
                    <div class="grow min-w-0">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $card['title'] ?? '—' }}</div>
                                <div class="text-xs text-gray-500">Total: ${{ number_format((float) ($card['amount'] ?? 0), 2) }}</div>
                            </div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                {{ number_format((int) ($card['count'] ?? 0)) }}
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
