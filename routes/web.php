<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PhysicalCountController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\POSController;
use Illuminate\Support\Facades\Route;


// RUTAS PÚBLICAS (ACCESIBLES SIN LOGIN)

// 1. La Landing Page oficial
Route::get('/', function () {
    return view('landing');
})->name('landing');

// 2. Pantalla de Inicio de Sesión
Route::get('/login', function () {
    return view('auth.login'); 
})->name('login');

// 3. REGISTRO PASO 1: Formulario de Perfil de Usuario (Datos Personales)
Route::get('/register', function () {
    return view('signupprofile');
})->name('register.step1');

// 4. REGISTRO PASO 2: Formulario de Perfil de la Tienda
Route::get('/register/step2', function () {
    return view('signupbusinessprofile');
})->name('register.step2');


// ENLACE: Recibe el Paso 1 y redirige automáticamente al Paso 2
Route::post('/register/step1', function () {
    return redirect()->route('register.step2');
})->name('register.step1.store');


// 4. REGISTRO PASO 2: Formulario de Perfil de la Tienda (Datos del Tenant)
Route::get('/register/step2', function () {
    return view('signupbusinessprofile');
})->name('register.step2');

// ENLACE FIN: Recibe el Paso 2 y mete al dueño directo al Dashboard
Route::post('/register/step2', function () {
    return redirect()->route('dashboard');
})->name('register.step2.store');


// RUTAS PRIVADAS (REQUIEREN AUTENTICACIÓN)
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
