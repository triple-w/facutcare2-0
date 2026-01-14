@php
use Illuminate\Support\Str;
@endphp

@forelse($facturas as $f)
    @php
        $serie = (string)($f->serie ?? '');
        $folio = (string)($f->folio ?? '');
        $serieFolio = trim($serie . ' ' . $folio) !== '' ? ($serie . '-' . $folio) : ('#' . $f->id);

        $razon = (string)($f->razon_social ?? '');
        $rfc   = (string)($f->rfc ?? '');
        $uuid  = (string)($f->uuid ?? '');
        $tipo  = (string)($f->nombre_comprobante ?? ($f->tipo_comprobante ?? ''));
        $estatus = (string)($f->estatus ?? '');

        $total = (float)($f->total ?? 0);

        $fecha = $f->fecha ?? $f->created_at ?? null;
        $fechaTxt = $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y H:i') : '—';

        // ✅ “Haystack” para filtrar en front (solo lo ya impreso)
        $haystack = Str::lower(trim(implode(' ', [
            $serie, $folio, $serieFolio, $razon, $rfc, $uuid, $tipo, $estatus,
            number_format($total, 2, '.', ''),
            $fechaTxt,
        ])));
    @endphp

    <tr data-search="{{ $haystack }}">
        {{-- Serie / Folio --}}
        <td class="px-4 py-3 font-medium">
            {{ $serieFolio }}
            @if(!empty($uuid))
                <div class="text-xs text-gray-500 font-mono">{{ $uuid }}</div>
            @endif
        </td>

        {{-- Cliente --}}
        <td class="px-4 py-3">
            {{ $razon !== '' ? $razon : '—' }}
            @if($rfc !== '')
                <div class="text-xs text-gray-500">{{ $rfc }}</div>
            @endif
        </td>

        {{-- Tipo de documento --}}
        <td class="px-4 py-3">
            {{ $tipo !== '' ? $tipo : '—' }}
            @if(!empty($f->tipo_comprobante))
                <div class="text-xs text-gray-500">Tipo CFDI: {{ $f->tipo_comprobante }}</div>
            @endif
        </td>

        {{-- Estatus --}}
        <td class="px-4 py-3">
            @php
                $st = strtoupper((string)($estatus));
                $badge = match($st) {
                    'TIMBRADA'   => 'bg-green-100 text-green-800',
                    'CANCELADA'  => 'bg-red-100 text-red-800',
                    'BORRADOR'   => 'bg-gray-100 text-gray-800',
                    ''           => 'bg-gray-100 text-gray-800',
                    default      => 'bg-yellow-100 text-yellow-800',
                };
                $label = $st !== '' ? $st : '—';
            @endphp

            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $badge }}">
                {{ $label }}
            </span>
        </td>

        {{-- Total --}}
        <td class="px-4 py-3 text-right">
            ${{ number_format($total, 2) }}
        </td>

        {{-- Fecha --}}
        <td class="px-4 py-3">
            {{ $fechaTxt }}
        </td>

        {{-- Acciones --}}
        <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-1">
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

                <a href="{{ route('facturas.xml', $f->id) }}"
                   title="Descargar XML"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    <span class="sr-only">XML</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/>
                    </svg>
                </a>

                <a href="{{ route('facturas.pdf', $f->id) }}"
                   title="Descargar PDF"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    <span class="sr-only">PDF</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v8m0 0l-3-3m3 3l3-3M7 7h10M9 3h6a2 2 0 012 2v4H7V5a2 2 0 012-2z"/>
                    </svg>
                </a>

                @if(!empty($f->acuse))
                    <a href="{{ route('facturas.acuse', $f->id) }}"
                       title="Descargar acuse"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        <span class="sr-only">Acuse</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </a>
                @endif

                <form class="inline" method="POST" action="{{ route('facturas.regenerarPdf', $f->id) }}">
                    @csrf
                    <button type="submit"
                            title="Regenerar PDF"
                            onclick="return confirm('¿Seguro de regenerar el PDF?');"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-yellow-500 text-black hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-300">
                        <span class="sr-only">Regenerar PDF</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.828-2M4 16a8 8 0 0014.828 2"/>
                        </svg>
                    </button>
                </form>

                @if(strtoupper((string)$f->estatus) === 'TIMBRADA')
                    <form class="inline" method="POST" action="{{ route('facturas.cancelar', $f->id) }}">
                        @csrf
                        <button type="submit"
                                title="Cancelar"
                                onclick="return confirm('¿Seguro de cancelar la factura?');"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400">
                            <span class="sr-only">Cancelar</span>
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
    <tr>
        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No hay facturas.</td>
    </tr>
@endforelse
