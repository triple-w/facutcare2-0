<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductosApiController extends Controller
{
    public function buscar(Request $request)
    {
        $userId = auth()->id();
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $rows = DB::table('productos as p')
            ->leftJoin('clave_prod_serv as cps', 'cps.id', '=', 'p.clave_prod_serv_id')
            ->leftJoin('clave_unidad as cu', 'cu.id', '=', 'p.clave_unidad_id')
            ->where('p.users_id', $userId)
            ->where(function ($w) use ($q) {
                $w->where('p.clave', 'like', "%{$q}%")
                  ->orWhere('p.descripcion', 'like', "%{$q}%");
            })
            ->orderBy('p.descripcion')
            ->limit(20)
            ->get([
                'p.id',
                'p.clave',
                'p.descripcion',
                'p.unidad',
                'p.precio',
                DB::raw('COALESCE(cps.clave, "") as clave_prod_serv'),
                DB::raw('COALESCE(cu.clave, "") as clave_unidad'),
            ]);

        return response()->json($rows);
    }
}
