<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\FoliosController;
use App\Http\Controllers\FacturasController;

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
        Route::get('facturas/create', [FacturasController::class, 'create'])->name('facturas.create');
        Route::post('facturas/preview', [FacturasController::class, 'preview'])->name('facturas.preview');

        //--nuevas rutas

        Route::get('/facturas/{id}/xml', [FacturasController::class, 'downloadXml'])->name('facturas.xml');
        Route::get('/facturas/{id}/pdf', [FacturasController::class, 'downloadPdf'])->name('facturas.pdf');
        Route::get('/facturas/{id}/ver', [FacturasController::class, 'show'])->name('facturas.ver');

        Route::get('/facturas/{id}/acuse', [FacturasController::class, 'downloadAcuse'])->name('facturas.acuse');
        Route::post('/facturas/{id}/regenerar-pdf', [FacturasController::class, 'regenerarPdf'])->name('facturas.regenerarPdf');

        Route::post('/facturas/{id}/cancelar', [FacturasController::class, 'cancelar'])->name('facturas.cancelar');

        // Complementos / Nóminas (placeholder)
        Route::view('complementos', 'pages/coming-soon')->name('complementos.index');
        Route::view('nominas', 'pages/coming-soon')->name('nominas.index');


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
