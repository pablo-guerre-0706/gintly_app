<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\RegisterWizardController;


// ==========================================
// RUTAS PÚBLICAS Y LANDING PAGE
// ==========================================
Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/landing', function () {
    return view('landing');
});

// Inicio de sesión (Vista)
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// ==========================================
// ASISTENTE DE REGISTRO MULTI-PASO (1-7)
// ==========================================
Route::prefix('register')->name('register.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('register.step', ['step' => 1]);
    })->name('index');

    Route::get('/step/{step}', [RegisterWizardController::class, 'showStep'])
        ->where('step', '[1-7]')
        ->name('step');

    Route::get('/step/{step}/store', [RegisterWizardController::class, 'storeStep'])
        ->where('step', '[1-7]')
        ->name('step.store');
});

// ==========================================
// PANEL DE ADMINISTRACIÓN (DASHBOARD)
// ==========================================
Route::middleware(['auth'])->group(function () {
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::view('/pos', 'pos.index')->name('pos.index');

    Route::view('/finance/cash-closing', 'finance.cash-closing')->name('finance.cash-closing');

    Route::view('/customers', 'customers.index')->name('customers.index');

    Route::view('/inventory/reconciliation', 'inventory.reconciliation')->name('inventory.reconciliation');

    Route::view('/catalog/products', 'catalog.products')->name('catalog.products');

});
