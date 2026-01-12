@php($isEdit = isset($cliente) && $cliente->exists)

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">RFC *</label>
        <input name="rfc" value="{{ old('rfc', $cliente->rfc) }}"
               class="w-full rounded-md border-gray-300" maxlength="30" required>
        @error('rfc') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
       <label class="block text-sm font-medium mb-1">Régimen Fiscal *</label>
        <select name="regimen_fiscal" class="w-full rounded-md border-gray-300" required>
            <option value="">Selecciona un régimen...</option>
            @foreach (config('sat.regimenes_fiscales') as $clave => $nombre)
                <option value="{{ $clave }}" @selected(old('regimen_fiscal', $cliente->regimen_fiscal) == $clave)>
                    {{ $clave }} - {{ $nombre }}
                </option>
            @endforeach
        </select>
        @error('regimen_fiscal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Razón Social *</label>
        <input name="razon_social" value="{{ old('razon_social', $cliente->razon_social) }}"
               class="w-full rounded-md border-gray-300" maxlength="200" required>
        @error('razon_social') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input name="email" value="{{ old('email', $cliente->email) }}"
               class="w-full rounded-md border-gray-300" maxlength="90" type="email">
        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Teléfono</label>
        <input name="telefono" value="{{ old('telefono', $cliente->telefono) }}"
               class="w-full rounded-md border-gray-300" maxlength="30">
        @error('telefono') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nombre de contacto</label>
        <input name="nombre_contacto" value="{{ old('nombre_contacto', $cliente->nombre_contacto) }}"
               class="w-full rounded-md border-gray-300" maxlength="150">
        @error('nombre_contacto') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<hr class="my-6">

<h3 class="text-base font-semibold mb-3">Dirección</h3>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Calle</label>
        <input name="calle" value="{{ old('calle', $cliente->calle) }}"
               class="w-full rounded-md border-gray-300" maxlength="100">
        @error('calle') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">No. Ext</label>
        <input name="no_ext" value="{{ old('no_ext', $cliente->no_ext) }}"
               class="w-full rounded-md border-gray-300" maxlength="20">
        @error('no_ext') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">No. Int</label>
        <input name="no_int" value="{{ old('no_int', $cliente->no_int) }}"
               class="w-full rounded-md border-gray-300" maxlength="20">
        @error('no_int') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Colonia</label>
        <input name="colonia" value="{{ old('colonia', $cliente->colonia) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('colonia') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Municipio</label>
        <input name="municipio" value="{{ old('municipio', $cliente->municipio) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('municipio') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Localidad</label>
        <input name="localidad" value="{{ old('localidad', $cliente->localidad) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('localidad') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Estado</label>
        <input name="estado" value="{{ old('estado', $cliente->estado) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('estado') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Código Postal</label>
        <input name="codigo_postal" value="{{ old('codigo_postal', $cliente->codigo_postal) }}"
               class="w-full rounded-md border-gray-300" maxlength="10">
        @error('codigo_postal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">País</label>
        <input name="pais" value="{{ old('pais', $cliente->pais) }}"
               class="w-full rounded-md border-gray-300" maxlength="30">
        @error('pais') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>
