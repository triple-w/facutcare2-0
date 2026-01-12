<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clientes</h2>

            <a href="{{ route('clientes.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-md text-sm">
                + Nuevo cliente
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="p-3 rounded-md bg-green-50 text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-4">
                <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
                    <form method="GET" class="flex gap-2 w-full">
                        <input name="q" value="{{ $q }}"
                            placeholder="Buscar por Razón Social, RFC, email o teléfono..."
                            class="w-full rounded-md border-gray-300">
                        <button class="px-4 py-2 bg-gray-100 rounded-md whitespace-nowrap">Buscar</button>
                    </form>

                    <a href="{{ route('clientes.create') }}"
                    class="inline-flex items-center justify-center px-4 py-2 bg-gray-900 text-white rounded-md text-sm whitespace-nowrap">
                        + Nuevo cliente
                    </a>
                </div>
            </div>


            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3">RFC</th>
                                <th class="text-left px-4 py-3">Razón Social</th>
                                <th class="text-left px-4 py-3">Email</th>
                                <th class="text-left px-4 py-3">Teléfono</th>
                                <th class="text-left px-4 py-3">Régimen</th>
                                <th class="text-right px-4 py-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($clientes as $c)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $c->rfc }}</td>
                                    <td class="px-4 py-3">{{ $c->razon_social }}</td>
                                    <td class="px-4 py-3">{{ $c->email }}</td>
                                    <td class="px-4 py-3">{{ $c->telefono }}</td>
                                    <td class="px-4 py-3">{{ $c->regimen_fiscal }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a class="text-blue-600 hover:underline"
                                           href="{{ route('clientes.edit', $c) }}">Editar</a>

                                        <form action="{{ route('clientes.destroy', $c) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('¿Eliminar este cliente?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline ml-3">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                        No hay clientes.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4">
                    {{ $clientes->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
