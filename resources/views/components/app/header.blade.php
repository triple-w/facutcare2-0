<header class="sticky top-0 before:absolute before:inset-0 before:backdrop-blur-md max-lg:before:bg-white/90 dark:max-lg:before:bg-gray-800/90 before:-z-10 z-30 {{ $variant === 'v2' || $variant === 'v3' ? 'before:bg-white after:absolute after:h-px after:inset-x-0 after:top-full after:bg-gray-200 dark:after:bg-gray-700/60 after:-z-10' : 'max-lg:shadow-xs lg:before:bg-gray-100/90 dark:lg:before:bg-gray-900/90' }} {{ $variant === 'v2' ? 'dark:before:bg-gray-800' : '' }} {{ $variant === 'v3' ? 'dark:before:bg-gray-900' : '' }}">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 {{ $variant === 'v2' || $variant === 'v3' ? '' : 'lg:border-b border-gray-200 dark:border-gray-700/60' }}">

            <!-- Header: Left side -->
            <div class="flex">
                
                <!-- Hamburger button -->
                <button
                    class="text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 lg:hidden"
                    @click.stop="sidebarOpen = !sidebarOpen"
                    aria-controls="sidebar"
                    :aria-expanded="sidebarOpen"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="5" width="16" height="2" />
                        <rect x="4" y="11" width="16" height="2" />
                        <rect x="4" y="17" width="16" height="2" />
                    </svg>
                </button>

            </div>

            <!-- Header: Right side -->
            <div class="flex items-center space-x-3">
                <div class="inline-flex items-center rounded-lg border border-gray-200 dark:border-gray-700/60 px-3 py-1.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">Timbres</span>
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {{ (int) (auth()->user()->timbres_disponibles ?? 0) }}
                    </span>
                </div>

                @php
                    $csdTone = $csdHealth['tone'] ?? 'red';
                    $csdClasses = match ($csdTone) {
                        'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
                        default => 'border-red-200 bg-red-50 text-red-700',
                    };
                @endphp
                <div class="inline-flex items-center rounded-lg border px-3 py-1.5 {{ $csdClasses }}">
                    <span class="text-xs mr-2">Sellos</span>
                    <span class="text-sm font-semibold">
                        {{ $csdHealth['text'] ?? 'Sin sellos' }}
                    </span>
                </div>

                <!-- User button -->
                <x-dropdown-profile align="right" />

            </div>

        </div>
    </div>
</header>
