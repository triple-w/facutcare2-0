<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas</h2>
            <a href="{{ route('facturas.create') }}" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">
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

            <div class="bg-white shadow-sm rounded-lg p-4">
                <form class="flex gap-2" method="GET">
                    <input class="w-full rounded-md border-gray-300" name="q" value="{{ $q }}"
                           placeholder="Buscar por cliente, RFC, UUID, folio o estatus...">
                    <button class="px-4 py-2 bg-gray-100 rounded-md">Buscar</button>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Comprobante</th>
                                <th class="px-4 py-3 text-left">Tipo</th>
                                <th class="px-4 py-3 text-left">Cliente</th>
                                <th class="px-4 py-3 text-left">Estatus</th>
                                <th class="px-4 py-3 text-left">UUID</th>
                                <th class="px-4 py-3 text-right">Conceptos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($facturas as $f)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $f->nombre_comprobante }}</td>
                                    <td class="px-4 py-3">{{ $f->tipo_comprobante }}</td>
                                    <td class="px-4 py-3">{{ $f->razon_social }}<div class="text-xs text-gray-500">{{ $f->rfc }}</div></td>
                                    <td class="px-4 py-3">{{ $f->estatus }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $f->uuid }}</td>
                                    <td class="px-4 py-3 text-right">{{ $f->detalles_count }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            {{-- VER (destacado) --}}
                                            <a href="{{ route('facturas.ver', $f->id) }}"
                                                title="Ver factura"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg !bg-blue-600 !text-white hover:!bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                                    <span class="sr-only">Ver</span>

                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                </a>


                                            {{-- XML --}}
                                            <a href="{{ route('facturas.xml', $f->id) }}"
                                            title="Descargar XML"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                <span class="sr-only">XML</span>
                                                {{-- File icon --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/>
                                                </svg>
                                            </a>

                                            {{-- PDF --}}
                                            <a href="{{ route('facturas.pdf', $f->id) }}"
                                            title="Descargar PDF"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                <span class="sr-only">PDF</span>
                                                {{-- Document Arrow Down icon --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 10v8m0 0l-3-3m3 3l3-3M7 7h10M9 3h6a2 2 0 012 2v4H7V5a2 2 0 012-2z"/>
                                                </svg>
                                            </a>

                                            {{-- ACUSE (solo si existe) --}}
                                            @if(!empty($f->acuse))
                                                <a href="{{ route('facturas.acuse', $f->id) }}"
                                                title="Descargar acuse"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                    <span class="sr-only">Acuse</span>
                                                    {{-- Badge Check icon --}}
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </a>
                                            @endif

                                            {{-- REGEN PDF --}}
                                            <form class="inline" method="POST" action="{{ route('facturas.regenerarPdf', $f->id) }}">
                                                @csrf
                                                <button type="submit"
                                                        title="Regenerar PDF"
                                                        onclick="return confirm('¿Seguro de regenerar el PDF?');"
                                                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-yellow-500 text-black hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-300">
                                                    <span class="sr-only">Regenerar PDF</span>
                                                    {{-- Refresh icon --}}
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.828-2M4 16a8 8 0 0014.828 2"/>
                                                    </svg>
                                                </button>
                                            </form>

                                            {{-- CANCELAR (si quieres mostrarla solo cuando TIMBRADA) --}}
                                            @if(strtoupper((string)$f->estatus) === 'TIMBRADA')
                                                <form class="inline" method="POST" action="{{ route('facturas.cancelar', $f->id) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            title="Cancelar"
                                                            onclick="return confirm('¿Seguro de cancelar la factura?');"
                                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400">
                                                        <span class="sr-only">Cancelar</span>
                                                        {{-- Ban icon --}}
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M18.364 5.636l-12.728 12.728M6.343 6.343a9 9 0 1012.728 12.728A9 9 0 006.343 6.343z"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay facturas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $facturas->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
