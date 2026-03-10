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

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 bg-white shadow-sm rounded-lg p-6">
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

                        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-start">
                            <div>
                                <label class="block text-sm font-medium mb-1">Logo</label>
                                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="block w-full text-sm text-gray-500">
                                <p class="mt-1 text-xs text-gray-500">Se guarda en `public/uploads/users_logos/thumbnails/{usuario}.png` para que el PAC y los PDFs lo consuman.</p>
                            </div>

                            @if ($logoUrl)
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <img src="{{ $logoUrl }}" alt="Logo actual" class="h-24 w-24 rounded-lg object-cover">
                                    <form method="POST" action="{{ route('configuracion.logo.destroy') }}" class="mt-3">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm text-red-600 hover:text-red-700">Eliminar logo</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 flex gap-2">
                            <button class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">Guardar información</button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="bg-white shadow-sm rounded-lg p-6">
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
