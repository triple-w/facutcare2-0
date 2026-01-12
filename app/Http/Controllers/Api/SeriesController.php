<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeriesController extends Controller
{
    public function next(Request $request)
    {
        $userId = auth()->id();

        // La vista debe mandar "INGRESO"/"EGRESO"/"TRASLADO"
        $tipo = strtoupper(trim((string) $request->query('tipo', $request->query('tipo_comprobante', 'I'))));

        $map = [
        'I' => 'INGRESO',
        'E' => 'EGRESO',
        'T' => 'TRASLADO',
        'N' => 'NOMINA',
        'P' => 'PAGO',
        ];

        $tipoFolio = $map[$tipo] ?? $tipo;


        $folioRow = DB::table('folios')
            ->where('users_id', $userId)
            ->where('tipo', $tipoFolio)
            ->orderBy('id')
            ->first();

        if (!$folioRow) {
            return response()->json([
                'ok' => false,
                'message' => "No existe serie/folio configurado para tipo {$tipo}. Ve a Catálogos > Folios.",
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'serie' => $folioRow->serie,
            'folio' => (int) $folioRow->folio,
        ]);
    }

}
