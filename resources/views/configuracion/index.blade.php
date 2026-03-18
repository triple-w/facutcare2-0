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
                                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5">
                                    <input id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="sr-only" onchange="window.handleLogoInputChange && window.handleLogoInputChange(this)">
                                    <input id="logo_cropped" type="hidden" name="logo_cropped">

                                    <div class="flex flex-wrap items-center gap-3">
                                        <label for="logo" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-violet-700 bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-violet-700">
                                            Subir foto
                                        </label>
                                        <span id="logo-selected-name" class="text-sm text-gray-500">
                                            {{ $logoUrl ? 'Logo actual cargado' : 'Ningún archivo seleccionado' }}
                                        </span>
                                    </div>

                                    <p class="mt-3 text-xs text-gray-600">
                                        De preferencia sube una imagen chica en formato JPG. Después de elegirla se abrirá un modal para recortarla y acomodarla en cuadro antes de guardar.
                                    </p>

                                    <div class="mt-5 flex flex-wrap items-start gap-5">
                                        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                                            <img id="logo-preview" src="{{ $logoUrl ?? '' }}" alt="Vista previa del logo" class="h-32 w-32 rounded-xl object-cover {{ $logoUrl ? '' : 'hidden' }}">
                                            <div id="logo-preview-empty" class="flex h-32 w-32 items-center justify-center rounded-xl bg-gray-100 text-center text-xs text-gray-400 {{ $logoUrl ? 'hidden' : '' }}">
                                                Aquí verás el logo recortado
                                            </div>
                                        </div>

                                        <div class="max-w-sm text-sm text-gray-600">
                                            <div class="font-medium text-gray-900">Salida del sistema</div>
                                            <p class="mt-1">Se genera una imagen nueva en `.jpg` con el recorte confirmado por el usuario.</p>
                                            <p class="mt-2">Además se conserva la miniatura `.png` para el PAC y para la vista previa dentro del sistema.</p>

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
    <div id="logo-modal" class="fixed inset-0 z-50 hidden">
        <div id="logo-modal-backdrop" class="absolute inset-0 bg-gray-900/70"></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-4xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Recortar logo</h3>
                        <p class="mt-1 text-sm text-gray-500">Ajusta tu imagen dentro del cuadro. La vista pequeña representa el resultado ideal de 50x50.</p>
                    </div>
                    <button type="button" id="logo-modal-close" class="rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700">Cerrar</button>
                </div>

                <div class="grid gap-6 px-6 py-5 lg:grid-cols-[minmax(0,1fr)_260px]">
                    <div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div id="logo-crop-stage" class="relative mx-auto aspect-square max-w-[420px] overflow-hidden rounded-2xl bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%),linear-gradient(-45deg,#f3f4f6_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#f3f4f6_75%),linear-gradient(-45deg,transparent_75%,#f3f4f6_75%)] bg-[length:20px_20px] bg-[position:0_0,0_10px,10px_-10px,-10px_0px]">
                                <img id="logo-crop-image" alt="Logo para recortar" class="absolute left-0 top-0 hidden max-w-none select-none" draggable="false">
                                <div class="pointer-events-none absolute inset-0 rounded-2xl ring-1 ring-inset ring-black/10"></div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="mb-2 flex items-center justify-between text-xs font-medium uppercase tracking-wide text-gray-500">
                                <span>Zoom</span>
                                <button type="button" id="logo-reset" class="rounded-md border border-gray-300 bg-white px-3 py-1 normal-case tracking-normal text-gray-700 transition hover:bg-gray-50">Restablecer</button>
                            </div>
                            <input id="logo-zoom" type="range" min="-100" max="200" step="1" value="0" class="w-full">
                            <div class="mt-1 flex justify-between text-[11px] text-gray-400">
                                <span>Alejar</span>
                                <span>0</span>
                                <span>Acercar</span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="logo-bg-color" class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500">Color de fondo</label>
                            <div class="flex items-center gap-3">
                                <input id="logo-bg-color" type="color" value="#ffffff" class="h-10 w-16 cursor-pointer rounded border border-gray-300 bg-white p-1">
                                <span id="logo-bg-label" class="text-sm text-gray-600">#FFFFFF</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-2xl border border-gray-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Vista ideal 50x50</div>
                            <div class="mt-3 flex items-center justify-center">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                    <img id="logo-modal-preview-50" alt="Vista previa 50x50" class="h-[50px] w-[50px] rounded-md object-cover">
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Vista final</div>
                            <div class="mt-3 flex items-center justify-center">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                    <img id="logo-modal-preview-large" alt="Vista previa grande" class="h-32 w-32 rounded-lg object-cover">
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            Mueve la imagen con el mouse y usa el zoom para acomodar el logo dentro del cuadro.
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" id="logo-modal-cancel" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancelar</button>
                    <button type="button" id="logo-modal-apply" class="inline-flex items-center justify-center rounded-md border border-violet-700 bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-violet-700">Usar este recorte</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
(() => {
    const input = document.getElementById('logo');
    const hidden = document.getElementById('logo_cropped');
    const preview = document.getElementById('logo-preview');
    const previewEmpty = document.getElementById('logo-preview-empty');
    const selectedName = document.getElementById('logo-selected-name');
    const modal = document.getElementById('logo-modal');
    const modalBackdrop = document.getElementById('logo-modal-backdrop');
    const modalClose = document.getElementById('logo-modal-close');
    const modalCancel = document.getElementById('logo-modal-cancel');
    const modalApply = document.getElementById('logo-modal-apply');
    const cropStage = document.getElementById('logo-crop-stage');
    const cropImage = document.getElementById('logo-crop-image');
    const zoom = document.getElementById('logo-zoom');
    const reset = document.getElementById('logo-reset');
    const bgColor = document.getElementById('logo-bg-color');
    const bgLabel = document.getElementById('logo-bg-label');
    const preview50 = document.getElementById('logo-modal-preview-50');
    const previewLarge = document.getElementById('logo-modal-preview-large');

    if (!input || !hidden || !preview || !previewEmpty || !selectedName || !modal || !modalBackdrop || !modalClose || !modalCancel || !modalApply || !cropStage || !cropImage || !zoom || !reset || !preview50 || !previewLarge) {
        return;
    }

    const state = {
        imgWidth: 0,
        imgHeight: 0,
        scale: 1,
        baseScale: 1,
        offsetX: 0,
        offsetY: 0,
        dragging: false,
        dragX: 0,
        dragY: 0,
        lastObjectUrl: null,
        croppedDataUrl: hidden.value || '',
        pendingFileName: '',
        hadPendingSelection: false,
        originalLabel: selectedName.textContent,
        background: (bgColor && bgColor.value) ? bgColor.value : '#ffffff',
    };

    function openModal() {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function discardPendingSelection() {
        if (!state.hadPendingSelection) {
            return;
        }

        input.value = '';
        state.hadPendingSelection = false;
        state.pendingFileName = '';
        selectedName.textContent = state.originalLabel;
    }

    function clampOffsets() {
        const stageSize = cropStage.clientWidth;
        const drawWidth = state.imgWidth * state.scale;
        const drawHeight = state.imgHeight * state.scale;

        if (drawWidth <= stageSize) {
            state.offsetX = (stageSize - drawWidth) / 2;
        } else {
            const minX = stageSize - drawWidth;
            const maxX = 0;
            state.offsetX = Math.min(maxX, Math.max(minX, state.offsetX));
        }

        if (drawHeight <= stageSize) {
            state.offsetY = (stageSize - drawHeight) / 2;
        } else {
            const minY = stageSize - drawHeight;
            const maxY = 0;
            state.offsetY = Math.min(maxY, Math.max(minY, state.offsetY));
        }
    }

    function applyStageBackground() {
        cropStage.style.backgroundColor = state.background;
        if (bgLabel) {
            bgLabel.textContent = state.background.toUpperCase();
        }
    }

    function scaleFromZoomValue(value) {
        const zoomValue = parseInt(value || '0', 10);
        return state.baseScale * Math.pow(1.015, zoomValue);
    }

    function buildCroppedDataUrl() {
        const stageSize = cropStage.clientWidth;
        if (!stageSize) {
            return '';
        }

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 320;
        const ctx = canvas.getContext('2d');
        const ratio = 320 / stageSize;

        ctx.fillStyle = state.background;
        ctx.fillRect(0, 0, 320, 320);
        ctx.drawImage(
            cropImage,
            state.offsetX * ratio,
            state.offsetY * ratio,
            state.imgWidth * state.scale * ratio,
            state.imgHeight * state.scale * ratio
        );

        return canvas.toDataURL('image/jpeg', 0.92);
    }

    function renderCrop() {
        if (!state.imgWidth || !state.imgHeight) {
            return;
        }

        clampOffsets();
        cropImage.style.width = `${state.imgWidth * state.scale}px`;
        cropImage.style.height = `${state.imgHeight * state.scale}px`;
        cropImage.style.transform = `translate(${state.offsetX}px, ${state.offsetY}px)`;

        const dataUrl = buildCroppedDataUrl();
        preview50.src = dataUrl;
        previewLarge.src = dataUrl;
    }

    function fitImage() {
        const stageSize = cropStage.clientWidth;
        if (!stageSize) {
            return;
        }
        state.baseScale = Math.min(stageSize / state.imgWidth, stageSize / state.imgHeight);
        zoom.value = '0';
        state.scale = scaleFromZoomValue(zoom.value);
        state.offsetX = (stageSize - (state.imgWidth * state.scale)) / 2;
        state.offsetY = (stageSize - (state.imgHeight * state.scale)) / 2;
        renderCrop();
    }

    function loadImage(src) {
        openModal();
        cropImage.onload = () => {
            state.imgWidth = cropImage.naturalWidth;
            state.imgHeight = cropImage.naturalHeight;
            cropImage.classList.remove('hidden');
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    fitImage();
                });
            });
        };
        cropImage.src = src;
    }

    function commitCrop() {
        const dataUrl = buildCroppedDataUrl();
        if (!dataUrl) {
            return;
        }
        state.croppedDataUrl = dataUrl;
        hidden.value = dataUrl;
        preview.src = dataUrl;
        preview.classList.remove('hidden');
        previewEmpty.classList.add('hidden');
        selectedName.textContent = `Recorte listo: ${state.pendingFileName || 'nuevo logo'}`;
        state.hadPendingSelection = false;
        closeModal();
    }

    function handleFileChangeFromInput(inputEl) {
        const [file] = inputEl.files || [];
        if (!file) {
            return;
        }

        state.hadPendingSelection = true;
        state.pendingFileName = file.name;
        selectedName.textContent = file.name;
        if (state.lastObjectUrl) {
            URL.revokeObjectURL(state.lastObjectUrl);
        }
        state.lastObjectUrl = URL.createObjectURL(file);
        loadImage(state.lastObjectUrl);
    }

    zoom.addEventListener('input', () => {
        if (!state.imgWidth || !state.imgHeight) {
            return;
        }

        const previousScale = state.scale;
        state.scale = scaleFromZoomValue(zoom.value);
        const stageSize = cropStage.clientWidth;
        const centerX = (stageSize / 2) - state.offsetX;
        const centerY = (stageSize / 2) - state.offsetY;
        const ratio = state.scale / previousScale;
        state.offsetX = (stageSize / 2) - (centerX * ratio);
        state.offsetY = (stageSize / 2) - (centerY * ratio);
        renderCrop();
    });

    reset.addEventListener('click', () => {
        if (!state.imgWidth || !state.imgHeight) {
            return;
        }
        fitImage();
    });

    if (bgColor) {
        bgColor.addEventListener('input', () => {
            state.background = bgColor.value || '#ffffff';
            applyStageBackground();
            if (state.imgWidth && state.imgHeight) {
                renderCrop();
            }
        });
    }

    cropStage.addEventListener('pointerdown', (event) => {
        if (cropImage.classList.contains('hidden')) {
            return;
        }
        state.dragging = true;
        state.dragX = event.clientX - state.offsetX;
        state.dragY = event.clientY - state.offsetY;
        cropStage.setPointerCapture(event.pointerId);
    });

    cropStage.addEventListener('pointermove', (event) => {
        if (!state.dragging) {
            return;
        }
        state.offsetX = event.clientX - state.dragX;
        state.offsetY = event.clientY - state.dragY;
        renderCrop();
    });

    function stopDragging(event) {
        if (!state.dragging) {
            return;
        }
        state.dragging = false;
        if (event && cropStage.hasPointerCapture(event.pointerId)) {
            cropStage.releasePointerCapture(event.pointerId);
        }
    }

    cropStage.addEventListener('pointerup', stopDragging);
    cropStage.addEventListener('pointercancel', stopDragging);
    modalBackdrop.addEventListener('click', () => {
        discardPendingSelection();
        closeModal();
    });
    modalClose.addEventListener('click', () => {
        discardPendingSelection();
        closeModal();
    });
    modalCancel.addEventListener('click', () => {
        discardPendingSelection();
        closeModal();
    });
    modalApply.addEventListener('click', commitCrop);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            discardPendingSelection();
            closeModal();
        }
    });
    input.addEventListener('change', () => handleFileChangeFromInput(input));
    applyStageBackground();
    window.handleLogoInputChange = handleFileChangeFromInput;
    window.factucareLogoCropper = {
        handleFileChange: () => handleFileChangeFromInput(input),
        openModal,
        closeModal,
    };
})();
</script>
