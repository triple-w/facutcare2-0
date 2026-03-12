<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Configuración fiscal</h2>
            <p class="mt-1 text-sm text-gray-500">Administra los datos del emisor, el logo y los sellos digitales del usuario.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div id="cuenta" class="bg-white shadow-sm rounded-lg p-6 scroll-mt-24">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Configuración de cuenta</h3>
                        <p class="mt-1 text-sm text-gray-500">Accesos rápidos para el perfil fiscal, el logo y los sellos digitales del usuario.</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="#rfc" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm text-white">Información de RFC</a>
                            <a href="#sellos" class="inline-flex items-center rounded-md bg-violet-600 px-3 py-2 text-sm text-white">Sellos digitales</a>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo actual" class="h-20 w-20 rounded-lg object-cover ring-1 ring-gray-200">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-500 ring-1 ring-gray-200">
                                Sin logo
                            </div>
                        @endif

                        <div class="text-sm text-gray-600">
                            <div><span class="font-medium text-gray-900">Usuario:</span> {{ auth()->user()->username ?? auth()->user()->name }}</div>
                            <div><span class="font-medium text-gray-900">RFC activo:</span> {{ $perfil['rfc'] ?: '—' }}</div>
                            <div><span class="font-medium text-gray-900">CSD:</span> {{ !empty(($documentos['ARCHIVO_CERTIFICADO'] ?? null)?->validado) && !empty(($documentos['ARCHIVO_LLAVE'] ?? null)?->validado) ? 'Validado' : 'Pendiente' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div id="rfc" class="xl:col-span-2 bg-white shadow-sm rounded-lg p-6 scroll-mt-24">
                    <h3 class="text-base font-semibold text-gray-900">Información del emisor</h3>
                    <p class="mt-1 text-sm text-gray-500">Estos datos alimentan `users_perfil` y `users_info_factura`, que hoy ya usa el timbrado.</p>

                    <form method="POST" action="{{ route('configuracion.perfil') }}" enctype="multipart/form-data" class="mt-6">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">RFC *</label>
                                <input name="rfc" value="{{ $perfil['rfc'] }}" class="w-full rounded-md border-gray-300" maxlength="30" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Régimen Fiscal *</label>
                                <select name="regimen_fiscal" class="w-full rounded-md border-gray-300" required>
                                    <option value="">Selecciona un régimen...</option>
                                    @foreach (config('sat.regimenes_fiscales') as $clave => $nombre)
                                        <option value="{{ $clave }}" @selected($perfil['regimen_fiscal'] == $clave)>{{ $clave }} - {{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">Razón Social *</label>
                                <input name="razon_social" value="{{ $perfil['razon_social'] }}" class="w-full rounded-md border-gray-300" maxlength="200" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Teléfono</label>
                                <input name="telefono" value="{{ $perfil['telefono'] }}" class="w-full rounded-md border-gray-300" maxlength="30">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Nombre de contacto</label>
                                <input name="nombre_contacto" value="{{ $perfil['nombre_contacto'] }}" class="w-full rounded-md border-gray-300" maxlength="150">
                            </div>
                        </div>

                        <hr class="my-6">

                        <h4 class="text-sm font-semibold text-gray-900 mb-4">Dirección fiscal</h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">Calle</label>
                                <input name="calle" value="{{ $perfil['calle'] }}" class="w-full rounded-md border-gray-300" maxlength="100">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">No. Ext</label>
                                <input name="no_ext" value="{{ $perfil['no_ext'] }}" class="w-full rounded-md border-gray-300" maxlength="20">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">No. Int</label>
                                <input name="no_int" value="{{ $perfil['no_int'] }}" class="w-full rounded-md border-gray-300" maxlength="20">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Colonia</label>
                                <input name="colonia" value="{{ $perfil['colonia'] }}" class="w-full rounded-md border-gray-300" maxlength="50">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Municipio</label>
                                <input name="municipio" value="{{ $perfil['municipio'] }}" class="w-full rounded-md border-gray-300" maxlength="50">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Localidad</label>
                                <input name="localidad" value="{{ $perfil['localidad'] }}" class="w-full rounded-md border-gray-300" maxlength="50">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Estado</label>
                                <input name="estado" value="{{ $perfil['estado'] }}" class="w-full rounded-md border-gray-300" maxlength="50">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Código Postal</label>
                                <input name="codigo_postal" value="{{ $perfil['codigo_postal'] }}" class="w-full rounded-md border-gray-300" maxlength="10">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">País</label>
                                <input name="pais" value="{{ $perfil['pais'] }}" class="w-full rounded-md border-gray-300" maxlength="30">
                            </div>
                        </div>

                        <hr class="my-6">

                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_280px] items-start">
                            <div>
                                <label class="block text-sm font-medium mb-2">Logo</label>
                                <div id="logo-cropper" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4">
                                    <input id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                                    <input id="logo_cropped" type="hidden" name="logo_cropped">

                                    <div class="flex flex-wrap items-center gap-3">
                                        <label for="logo" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-sky-700 bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-700">
                                            Seleccionar logo
                                        </label>
                                        <button type="button" id="logo-reset" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
                                            Restablecer recorte
                                        </button>
                                    </div>

                                    <p class="mt-3 text-xs text-gray-600">
                                        De preferencia sube una imagen chica en formato JPG. El sistema la recorta en cuadrado y la guarda optimizada para facturas.
                                    </p>

                                    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                                        <div>
                                            <div class="rounded-xl border border-gray-200 bg-white p-4">
                                                <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Recorte</div>
                                                <div class="mx-auto w-full max-w-[320px]">
                                                    <div id="logo-crop-stage" class="relative aspect-square overflow-hidden rounded-xl bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%),linear-gradient(-45deg,#f3f4f6_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#f3f4f6_75%),linear-gradient(-45deg,transparent_75%,#f3f4f6_75%)] bg-[length:20px_20px] bg-[position:0_0,0_10px,10px_-10px,-10px_0px]">
                                                        <img id="logo-crop-image" src="{{ $logoUrl ?? '' }}" alt="Recorte del logo" class="absolute left-0 top-0 hidden max-w-none select-none" draggable="false">
                                                        <div id="logo-crop-placeholder" class="flex h-full items-center justify-center text-center text-sm text-gray-400">
                                                            Elige una imagen para ajustar el recorte
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <label for="logo-zoom" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Zoom</label>
                                                <input id="logo-zoom" type="range" min="1" max="3" step="0.01" value="1" class="w-full">
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                                            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Vista previa</div>
                                            <div class="flex items-center justify-center">
                                                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                                    <img id="logo-preview" src="{{ $logoUrl ?? '' }}" alt="Vista previa del logo" class="h-36 w-36 rounded-lg object-cover {{ $logoUrl ? '' : 'hidden' }}">
                                                    <div id="logo-preview-empty" class="flex h-36 w-36 items-center justify-center rounded-lg bg-gray-100 text-center text-xs text-gray-400 {{ $logoUrl ? 'hidden' : '' }}">
                                                        Aquí verás cómo quedará el logo
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="mt-3 text-xs text-gray-500">
                                                Tamaño de salida: JPG cuadrado optimizado. También se genera la miniatura PNG que usa el PAC.
                                            </p>

                                            @if ($logoUrl)
                                                <form method="POST" action="{{ route('configuracion.logo.destroy') }}" class="mt-4">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="inline-flex items-center justify-center rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100">Eliminar logo actual</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-2">
                            <button class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">Guardar información</button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div id="sellos" class="bg-white shadow-sm rounded-lg p-6 scroll-mt-24">
                        <h3 class="text-base font-semibold text-gray-900">Sellos digitales</h3>
                        <p class="mt-1 text-sm text-gray-500">Valida el `.cer` y el `.key`, los guarda en servidor y genera sus `.pem`.</p>

                        <form method="POST" action="{{ route('configuracion.csd') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                            @csrf

                            <div>
                                <label class="block text-sm font-medium mb-1">RFC del certificado *</label>
                                <input name="rfc" value="{{ old('rfc', $perfil['rfc']) }}" class="w-full rounded-md border-gray-300" maxlength="30" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Contraseña de la llave *</label>
                                <input type="password" name="password" class="w-full rounded-md border-gray-300" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Archivo .cer *</label>
                                <input type="file" name="archivo_certificado" accept=".cer" class="block w-full text-sm text-gray-500" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Archivo .key *</label>
                                <input type="file" name="archivo_llave" accept=".key" class="block w-full text-sm text-gray-500" required>
                            </div>

                            <button class="w-full px-4 py-2 bg-violet-600 text-white rounded-md text-sm">Validar y guardar sellos</button>
                        </form>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-base font-semibold text-gray-900">Estado actual</h3>
                        <div class="mt-4 space-y-4 text-sm">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900">Certificado</div>
                                @php($cer = $documentos['ARCHIVO_CERTIFICADO'] ?? null)
                                <div class="mt-2 text-gray-600">{{ $cer?->_name ?? 'No cargado' }}</div>
                                <div class="mt-1 text-xs text-gray-500">No. certificado: {{ $cer->numero_certificado ?? '—' }}</div>
                                <div class="mt-1 text-xs text-gray-500">Vigencia: {{ $cer->vigencia ?? '—' }}</div>
                                <div class="mt-2 text-xs">
                                    @if (!empty($cer?->validado))
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">Validado</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-gray-700">Pendiente</span>
                                    @endif
                                </div>
                                @if ($cer)
                                    <form method="POST" action="{{ route('configuracion.documentos.destroy', $cer->id) }}" class="mt-3">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Eliminar certificado</button>
                                    </form>
                                @endif
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900">Llave privada</div>
                                @php($key = $documentos['ARCHIVO_LLAVE'] ?? null)
                                <div class="mt-2 text-gray-600">{{ $key?->_name ?? 'No cargada' }}</div>
                                <div class="mt-1 text-xs text-gray-500">Vigencia: {{ $key->vigencia ?? '—' }}</div>
                                <div class="mt-2 text-xs">
                                    @if (!empty($key?->validado))
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">Validada</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-gray-700">Pendiente</span>
                                    @endif
                                </div>
                                @if ($key)
                                    <form method="POST" action="{{ route('configuracion.documentos.destroy', $key->id) }}" class="mt-3">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Eliminar llave</button>
                                    </form>
                                @endif
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900">Password CSD</div>
                                <div class="mt-2 text-gray-600">{{ !empty($infoFactura?->password) ? 'Guardada' : 'No guardada' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('logo');
    const hidden = document.getElementById('logo_cropped');
    const image = document.getElementById('logo-crop-image');
    const preview = document.getElementById('logo-preview');
    const previewEmpty = document.getElementById('logo-preview-empty');
    const placeholder = document.getElementById('logo-crop-placeholder');
    const stage = document.getElementById('logo-crop-stage');
    const zoom = document.getElementById('logo-zoom');
    const reset = document.getElementById('logo-reset');

    if (!input || !hidden || !image || !preview || !placeholder || !stage || !zoom || !reset) {
        return;
    }

    const state = {
        imgWidth: 0,
        imgHeight: 0,
        scale: 1,
        minScale: 1,
        offsetX: 0,
        offsetY: 0,
        dragging: false,
        dragX: 0,
        dragY: 0,
    };

    function clampOffsets() {
        const stageSize = stage.clientWidth;
        const drawWidth = state.imgWidth * state.scale;
        const drawHeight = state.imgHeight * state.scale;
        const minX = Math.min(0, stageSize - drawWidth);
        const minY = Math.min(0, stageSize - drawHeight);
        const maxX = Math.max(0, stageSize - drawWidth);
        const maxY = Math.max(0, stageSize - drawHeight);
        state.offsetX = Math.min(maxX, Math.max(minX, state.offsetX));
        state.offsetY = Math.min(maxY, Math.max(minY, state.offsetY));
    }

    function render() {
        if (!state.imgWidth || !state.imgHeight) {
            return;
        }

        clampOffsets();
        image.style.width = `${state.imgWidth * state.scale}px`;
        image.style.height = `${state.imgHeight * state.scale}px`;
        image.style.transform = `translate(${state.offsetX}px, ${state.offsetY}px)`;

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 320;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, 320, 320);
        ctx.drawImage(
            image,
            state.offsetX * (320 / stage.clientWidth),
            state.offsetY * (320 / stage.clientWidth),
            state.imgWidth * state.scale * (320 / stage.clientWidth),
            state.imgHeight * state.scale * (320 / stage.clientWidth)
        );

        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        hidden.value = dataUrl;
        preview.src = dataUrl;
        preview.classList.remove('hidden');
        previewEmpty.classList.add('hidden');
    }

    function fitImage() {
        const stageSize = stage.clientWidth;
        state.minScale = Math.max(stageSize / state.imgWidth, stageSize / state.imgHeight);
        state.scale = state.minScale;
        zoom.min = state.minScale.toFixed(2);
        zoom.max = Math.max(state.minScale + 2, state.minScale * 3).toFixed(2);
        zoom.value = state.scale.toFixed(2);
        state.offsetX = (stageSize - state.imgWidth * state.scale) / 2;
        state.offsetY = (stageSize - state.imgHeight * state.scale) / 2;
        render();
    }

    function setImageSource(src) {
        image.onload = () => {
            state.imgWidth = image.naturalWidth;
            state.imgHeight = image.naturalHeight;
            image.classList.remove('hidden');
            placeholder.classList.add('hidden');
            fitImage();
        };
        image.src = src;
    }

    input.addEventListener('change', (event) => {
        const [file] = event.target.files || [];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => setImageSource(reader.result);
        reader.readAsDataURL(file);
    });

    zoom.addEventListener('input', () => {
        if (!state.imgWidth || !state.imgHeight) {
            return;
        }

        const previousScale = state.scale;
        state.scale = Math.max(state.minScale, parseFloat(zoom.value || '1'));
        const stageSize = stage.clientWidth;
        const centerX = (stageSize / 2) - state.offsetX;
        const centerY = (stageSize / 2) - state.offsetY;
        const ratio = state.scale / previousScale;
        state.offsetX = (stageSize / 2) - centerX * ratio;
        state.offsetY = (stageSize / 2) - centerY * ratio;
        render();
    });

    reset.addEventListener('click', () => {
        if (!state.imgWidth || !state.imgHeight) {
            return;
        }
        fitImage();
    });

    stage.addEventListener('pointerdown', (event) => {
        if (image.classList.contains('hidden')) {
            return;
        }
        state.dragging = true;
        state.dragX = event.clientX - state.offsetX;
        state.dragY = event.clientY - state.offsetY;
        stage.setPointerCapture(event.pointerId);
    });

    stage.addEventListener('pointermove', (event) => {
        if (!state.dragging) {
            return;
        }
        state.offsetX = event.clientX - state.dragX;
        state.offsetY = event.clientY - state.dragY;
        render();
    });

    function stopDragging(event) {
        if (!state.dragging) {
            return;
        }
        state.dragging = false;
        if (event && stage.hasPointerCapture(event.pointerId)) {
            stage.releasePointerCapture(event.pointerId);
        }
    }

    stage.addEventListener('pointerup', stopDragging);
    stage.addEventListener('pointercancel', stopDragging);

    if (image.getAttribute('src')) {
        setImageSource(image.getAttribute('src'));
    }
});
</script>
@endpush
