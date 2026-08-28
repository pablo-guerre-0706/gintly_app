<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PhysicalCountController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\POSController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // Verdadera conexion Dashboard principal
    // Route::get('/dashboard', [DashboardController::class, 'index'])
    // ->name('dashboard');
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // puntos de venta
    Route::get('/pos', function () {
        return view('pos.index');
    })->middleware('auth')->name('pos.index');

    // Inventario: Conciliación y Stocks
    Route::get('/inventory/reconciliation', [PhysicalCountController::class, 'index'])->name('inventory.reconciliation');

    // Finanzas, cierre de caja
    Route::get('/finance/cash-closing', function () {
        return view('finance.cash-closing');
    })->middleware('auth')->name('finance.cash-closing');

    // Clientes y Fidelidad
    Route::get('/customers', function () {
        return view('customers.index');
    })->middleware('auth')->name('customers.index');

    // Catálogo de Productos y Datos Maestros
    Route::get('/catalog/products', [ProductController::class, 'products'])->name('catalog.products');
});