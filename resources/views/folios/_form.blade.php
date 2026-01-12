@php($f = $folio ?? null)

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <div>
        <label class="block text-sm font-medium mb-1">Tipo de comprobante *</label>
        <select name="tipo" class="w-full rounded-md border-gray-300" required>
            <option value="">Selecciona un tipo...</option>
            @foreach (config('sat.tipos_comprobante') as $clave => $nombre)
                <option value="{{ $clave }}" @selected(old('tipo', $f->tipo) == $clave)>
                    {{ $nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Serie *</label>
        <input name="serie" value="{{ old('serie', $f->serie) }}"
               class="w-full rounded-md border-gray-300" maxlength="20" required>
        @error('serie') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Folio inicial *</label>
        <input name="folio" type="number" min="0"
               value="{{ old('folio', $f->folio ?? 0) }}"
               class="w-full rounded-md border-gray-300" required>
        @error('folio') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

</div>
