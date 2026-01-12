<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ProductosController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $productos = Producto::query()
            ->with([
                'prodServ:id,clave,descripcion',
                'unidadSat:id,clave,descripcion',
            ])
            ->forUser(auth()->id())
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('descripcion', 'like', "%{$q}%")
                       ->orWhere('clave', 'like', "%{$q}%");
                });
            })
            ->orderBy('descripcion')
            ->paginate(20)
            ->withQueryString();

        return view('productos.index', compact('productos', 'q'));
    }

    public function create()
    {
        $producto = new Producto([
            'precio' => 0,
        ]);

        return view('productos.create', compact('producto'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['users_id'] = auth()->id();

        Producto::create($data);

        return redirect()->route('productos.index')->with('status', 'Producto creado correctamente.');
    }

    public function edit(Producto $producto)
    {
        $this->authorizeOwner($producto);
        return view('productos.edit', compact('producto'));
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizeOwner($producto);

        $data = $this->validated($request);

        // Nunca cambiar dueño
        unset($data['users_id']);

        $producto->update($data);

        return redirect()->route('productos.index')->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        $this->authorizeOwner($producto);
        $producto->delete();

        return redirect()->route('productos.index')->with('status', 'Producto eliminado correctamente.');
    }

    // =========================
    // Endpoints tipo iKontrol (Alpine search)
    // =========================

    public function searchClaveProdServ(Request $request)
    {
        // para precargar en edit: ?id=123
        if ($request->filled('id')) {
            return DB::table('clave_prod_serv')
                ->where('id', (int) $request->query('id'))
                ->limit(1)
                ->get(['id', 'clave', 'descripcion']);
        }

        $term = trim((string) $request->query('term', ''));

        if ($term === '' || mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return DB::table('clave_prod_serv')
            ->where(function ($q) use ($term) {
                $q->where('clave', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%");
            })
            ->orderBy('clave')
            ->limit(20)
            ->get(['id', 'clave', 'descripcion']);
    }

    public function searchClaveUnidad(Request $request)
    {
        // para precargar en edit: ?id=123
        if ($request->filled('id')) {
            return DB::table('clave_unidad')
                ->where('id', (int) $request->query('id'))
                ->limit(1)
                ->get(['id', 'clave', 'descripcion']);
        }

        $term = trim((string) $request->query('term', ''));

        if ($term === '' || mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return DB::table('clave_unidad')
            ->where(function ($q) use ($term) {
                $q->where('clave', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%");
            })
            ->orderBy('clave')
            ->limit(20)
            ->get(['id', 'clave', 'descripcion']);
    }

    private function authorizeOwner(Producto $producto): void
    {
        abort_unless((int) $producto->users_id === (int) auth()->id(), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'clave' => ['required', 'string', 'max:30'],
            'unidad' => ['required', 'string', 'max:30'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:150'],

            'clave_prod_serv_id' => ['nullable', 'integer', Rule::exists('clave_prod_serv', 'id')],
            'clave_unidad_id' => ['nullable', 'integer', Rule::exists('clave_unidad', 'id')],
        ]);
    }
}
