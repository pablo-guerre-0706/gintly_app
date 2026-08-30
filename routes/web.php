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

// Inicio de sesión
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// ==========================================
// ASISTENTE DE REGISTRO MULTI-PASO (1-9)
// ==========================================
Route::prefix('register')->name('register.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('register.step', ['step' => 1]);
    })->name('index');

    // Muestra la vista del paso correspondiente (Ej: /register/step/3)
    Route::get('/step/{step}', [RegisterWizardController::class, 'showStep'])
        ->where('step', '[1-9]')
        ->name('step');

    // Procesa los datos enviados por GET desde el formulario y avanza de paso (Ej: /register/step/3/store)
    Route::get('/step/{step}/store', [RegisterWizardController::class, 'storeStep'])
        ->where('step', '[1-9]')
        ->name('step.store');
});

// ==========================================
// PANEL DE ADMINISTRACIÓN (DASHBOARD)
// ==========================================
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');