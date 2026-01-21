<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\FoliosController;
use App\Http\Controllers\FacturasController;
use App\Http\Controllers\ComplementosController;

use App\Http\Controllers\Api\SeriesController;
use App\Http\Controllers\Api\ProductosApiController;
use App\Http\Controllers\Api\SatController;

/*
|--------------------------------------------------------------------------
| Web Routes (FactuCare - limpio)
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/dashboard');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // 1) Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2) Catálogos
    Route::prefix('catalogos')->group(function () {

        Route::resource('clientes', ClientesController::class)->except(['show']);
        Route::resource('productos', ProductosController::class)->except(['show']);
        Route::resource('folios', FoliosController::class)->except(['show']);

        // Empleados (placeholder por ahora)
        Route::view('empleados', 'pages/coming-soon')->name('empleados.index');

        // Drawer “Editar cliente” (si lo usas en facturas)
        Route::post('clientes/{cliente}', [ClientesController::class, 'updateJson'])
            ->name('clientes.updateJson');

        // SAT buscadores (si los usas en productos)
        Route::get('search/prodserv', [ProductosController::class, 'searchClaveProdServ'])->name('catalogos.search.prodserv');
        Route::get('search/unidades', [ProductosController::class, 'searchClaveUnidad'])->name('catalogos.search.unidades');
    });

    // 3) Facturas / Documentos
    Route::prefix('documentos')->group(function () {

        Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
        Route::get('facturas/nueva', [FacturasController::class, 'nueva'])->name('facturas.nueva');
        Route::get('facturas/create', [FacturasController::class, 'create'])->name('facturas.create');
        Route::post('facturas/preview', [FacturasController::class, 'preview'])->name('facturas.preview');
        Route::get('facturas/preview', [FacturasController::class, 'previewGet'])->name('facturas.preview.get');
        Route::post('facturas/timbrar', [FacturasController::class, 'timbrar'])->name('facturas.timbrar');
        //--nuevas rutas
        Route::get('/facturas/{id}/xml', [FacturasController::class, 'downloadXml'])->name('facturas.xml');
        Route::get('/facturas/{id}/pdf', [FacturasController::class, 'downloadPdf'])->name('facturas.pdf');
        Route::get('/facturas/{id}/ver', [FacturasController::class, 'show'])->name('facturas.ver');
        Route::get('/facturas/{id}/acuse', [FacturasController::class, 'downloadAcuse'])->name('facturas.acuse');
        Route::post('/facturas/{id}/regenerar-pdf', [FacturasController::class, 'regenerarPdf'])->name('facturas.regenerarPdf');

        Route::post('/facturas/{id}/cancelar', [FacturasController::class, 'cancelar'])->name('facturas.cancelar');


        Route::get('/facturas/chunk', [FacturasController::class, 'indexChunk'])->name('facturas.indexChunk');
        Route::get('/facturas/rows', [\App\Http\Controllers\Users\FacturasController::class, 'rows'])->name('facturas.rows');


        // Complementos de pago
        Route::get('complementos', [ComplementosController::class, 'index'])->name('complementos.index');
        Route::get('complementos/nueva', [ComplementosController::class, 'nueva'])->name('complementos.nueva');
        Route::get('complementos/create', [ComplementosController::class, 'create'])->name('complementos.create');
        Route::post('complementos/preview', [ComplementosController::class, 'preview'])->name('complementos.preview');

        // AJAX: facturas con saldo insoluto por cliente
        Route::get('complementos/facturas-pendientes', [ComplementosController::class, 'facturasPendientes'])
            ->name('complementos.facturasPendientes');


        Route::view('nominas', 'pages/coming-soon')->name('nominas.index');


    });

    Route::middleware(['web','auth'])->prefix('api')->group(function () {
        Route::get('series/next', [SeriesController::class, 'next'])->name('api.series.next');
        Route::get('productos/buscar', [ProductosApiController::class, 'buscar'])->name('api.productos.buscar');
        Route::get('sat/clave-prod-serv', [App\Http\Controllers\Api\SatController::class, 'prodServ']);
        Route::get('sat/clave-unidad', [App\Http\Controllers\Api\SatController::class, 'unidad']);
    });


    // 4) Reportes
    Route::view('/reportes', 'pages/coming-soon')->name('reportes.index');

    // 5) Configuración
    Route::view('/configuracion', 'pages/coming-soon')->name('configuracion.index');

    // APIs (usan sesión, no tokens)
    Route::prefix('api')->group(function () {
        Route::get('series/next', [SeriesController::class, 'next']);
        Route::get('productos/buscar', [ProductosApiController::class, 'buscar']);
        Route::get('sat/clave-prod-serv', [SatController::class, 'prodServ']);
        Route::get('sat/clave-unidad', [SatController::class, 'unidad']);
    });

    // Fallback
    Route::fallback(function () {
        return view('pages/utility/404');
    });
});
