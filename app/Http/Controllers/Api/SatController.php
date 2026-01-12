<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SatController extends Controller
{
    public function prodServ(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 3) return response()->json([]);

        $rows = DB::table('clave_prod_serv')
            ->where('clave', 'like', "%{$q}%")
            ->orWhere('descripcion', 'like', "%{$q}%")
            ->orderBy('clave')
            ->limit(25)
            ->get(['id','clave','descripcion']);

        return response()->json($rows);
    }

    public function unidad(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);

        $rows = DB::table('clave_unidad')
            ->where('clave', 'like', "%{$q}%")
            ->orWhere('descripcion', 'like', "%{$q}%")
            ->orderBy('clave')
            ->limit(25)
            ->get(['id','clave','descripcion','unidad']); // unidad opcional si existe columna

        return response()->json($rows);
    }
}
