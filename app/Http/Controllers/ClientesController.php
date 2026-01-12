<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ClientesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $clientes = Cliente::query()
            ->forUser(auth()->id())
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('razon_social', 'like', "%{$q}%")
                       ->orWhere('rfc', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%")
                       ->orWhere('telefono', 'like', "%{$q}%");
                });
            })
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes', 'q'));
    }

    public function create()
    {
        $cliente = new Cliente([
            'regimen_fiscal' => '',
            'pais' => 'MEX',
        ]);

        return view('clientes.create', compact('cliente'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['users_id'] = auth()->id();

        Cliente::create($data);

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente creado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $this->authorizeOwner($cliente);

        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->authorizeOwner($cliente);

        $data = $this->validated($request);

        // nunca permitir cambiar el dueño
        unset($data['users_id']);

        $cliente->update($data);

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $this->authorizeOwner($cliente);

        $cliente->delete();

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente eliminado correctamente.');
    }

    private function authorizeOwner(Cliente $cliente): void
    {
        abort_unless((int) $cliente->users_id === (int) auth()->id(), 403);
    }

    public function updateJson(Request $request, $clienteId)
    {
        $userId = auth()->id();

        // Solo permite editar clientes del usuario
        $cliente = DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $data = $request->only([
            'rfc','razon_social','email','calle','no_ext','no_int','colonia',
            'localidad','estado','codigo_postal','pais'
        ]);

        DB::table('clientes')
            ->where('id', $clienteId)
            ->update($data);

        $updated = DB::table('clientes')->where('id', $clienteId)->first();

        return response()->json($updated);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'rfc'             => ['required', 'string', 'max:30'],
            'razon_social'    => ['required', 'string', 'max:200'],

            'calle'           => ['nullable', 'string', 'max:100'],
            'no_ext'          => ['nullable', 'string', 'max:20'],
            'no_int'          => ['nullable', 'string', 'max:20'],
            'colonia'         => ['nullable', 'string', 'max:50'],
            'municipio'       => ['nullable', 'string', 'max:50'],
            'localidad'       => ['nullable', 'string', 'max:50'],
            'estado'          => ['nullable', 'string', 'max:50'],
            'codigo_postal'   => ['nullable', 'string', 'max:10'],
            'pais'            => ['nullable', 'string', 'max:30'],

            'telefono'        => ['nullable', 'string', 'max:30'],
            'nombre_contacto' => ['nullable', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:90'],

            // en tu tabla es NOT NULL, default ''
            'regimen_fiscal'  => ['required',
            'string',
            'max:5',
            Rule::in(array_keys(config('sat.regimenes_fiscales'))),],
        ]);
    }
}
