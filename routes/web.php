<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PhysicalCountController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\POSController;
use Illuminate\Support\Facades\Route;


// Web Routes
Route::middleware(['auth'])->group(function () {

    // Verdadera conexion Dashboard principal
    // Route::get('/dashboard', [DashboardController::class, 'index'])
    // ->name('dashboard');
    
    // La Landing Page oficial
    Route::get('/', function () {
        return view('landing');
    })->name('landing');

    // Rutas temporales del asistente de registro, view:perfil de usuario
    Route::get('/register', function () {
        return view('singupprofile');
    })->name('register');

    // Paso 1, alias por consistencia visual
    Route::get('/register/step1', function () {
        return view('singupprofile');
    })->name('register.step1');

    // Paso 2: perfil de la tienda
    Route::get('/register/step2', function () {
        return view('singupbusinessprofile');
    })->name('register.step2');

    // Rutas temporales de envío, simuladas para evitar errores al presionar botones, al enviar paso 1 salta al paso 2
    Route::post('/register/step1', function () {
        return redirect()->route('register.step2');
    })->name('register.step1.store');

    Route::post('/register/step2', function () {
        return response()->json(['message' => 'Simulación de registro exitosa']);
    })->name('register.step2.store');

    // Ruta temporal de inicio de sesión (lo requieren los botones de la Landing)
    Route::get('/login', function () {
        return "Pantalla de Login (Pendiente de enlazar al backend)";
    })->name('login');

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