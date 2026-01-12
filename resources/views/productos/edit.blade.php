<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar producto</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('productos.update', $producto) }}">
                    @csrf
                    @method('PUT')
                    @include('productos._form', ['producto' => $producto])

                    <div class="mt-6 flex gap-2">
                        <button class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm">
                            Actualizar
                        </button>
                        <a href="{{ route('productos.index') }}" class="px-4 py-2 bg-gray-100 rounded-md text-sm">
                            Volver
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
