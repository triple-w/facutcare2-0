<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class FoliosController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $folios = Folio::query()
            ->forUser(auth()->id())
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('tipo', 'like', "%{$q}%")
                       ->orWhere('serie', 'like', "%{$q}%")
                       ->orWhere('folio', 'like', "%{$q}%");
                });
            })
            ->orderBy('tipo')
            ->orderBy('serie')
            ->paginate(20)
            ->withQueryString();

        return view('folios.index', compact('folios', 'q'));
    }

    public function create()
    {
        $folio = new Folio([
            'tipo' => 'INGRESO',
            'serie' => 'A',
            'folio' => 1,
        ]);

        return view('folios.create', compact('folio'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['users_id'] = auth()->id();

        // Evitar duplicados por usuario/tipo/serie (opcional pero recomendable)
        $exists = Folio::query()
            ->forUser(auth()->id())
            ->where('tipo', $data['tipo'])
            ->where('serie', $data['serie'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['serie' => 'Ya existe un folio con ese tipo y serie.'])
                ->withInput();
        }

        Folio::create($data);

        return redirect()->route('folios.index')->with('status', 'Folio creado correctamente.');
    }

    public function edit(Folio $folio)
    {
        $this->authorizeOwner($folio);
        return view('folios.edit', compact('folio'));
    }

    public function update(Request $request, Folio $folio)
    {
        $this->authorizeOwner($folio);

        $data = $this->validated($request);

        // Evitar duplicados por usuario/tipo/serie (ignorando el actual)
        $exists = Folio::query()
            ->forUser(auth()->id())
            ->where('tipo', $data['tipo'])
            ->where('serie', $data['serie'])
            ->where('id', '!=', $folio->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['serie' => 'Ya existe un folio con ese tipo y serie.'])
                ->withInput();
        }

        $folio->update($data);

        return redirect()->route('folios.index')->with('status', 'Folio actualizado correctamente.');
    }

    public function destroy(Folio $folio)
    {
        $this->authorizeOwner($folio);
        $folio->delete();

        return redirect()->route('folios.index')->with('status', 'Folio eliminado correctamente.');
    }

    private function authorizeOwner(Folio $folio): void
    {
        abort_unless((int) $folio->users_id === (int) auth()->id(), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', 'string', 'max:30', Rule::in(array_keys(config('sat.tipos_comprobante')))],
            'serie' => ['required', 'string', 'max:20'],
            'folio' => ['required', 'integer', 'min:0'],
        ]);
    }
}
