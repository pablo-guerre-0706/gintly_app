<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\RegisterWizardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;





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
//=======================
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    // Intentar autenticar localmente
    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        
        // Enlace directo forzado a la ruta del dashboard para evitar bucles
        return redirect()->route('dashboard');
    }

    return back()->withErrors(['email' => 'Credenciales incorrectas.'])->withInput();
});

// RUTA DE ACCESO DIRECTO TEMPORAL
Route::get('/entrar-directo', function () {
    // 1. Crear un objeto de usuario falso directamente en la memoria (Bypass de MySQL)
    $fakeUser = new User();
    $fakeUser->id = 1;
    $fakeUser->business_id = 1;
    $fakeUser->name = 'Evaluador Gintly';
    $fakeUser->email = 'propietario@gintly.test';

    // 2. Activar la sesión web en Laragon instantáneamente
    Auth::login($fakeUser);
    
    // 3. Forzar el viaje directo a la vista del Dashboard
    return redirect()->route('dashboard');
});

// ==========================================
// ASISTENTE DE REGISTRO MULTI-PASO (1-7)
// ==========================================
Route::prefix('register')->name('register.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('register.step', ['step' => 1]);
    })->name('index');

    // Muestra la vista del paso correspondiente (Ej: /register/step/3)
    Route::get('/step/{step}', [RegisterWizardController::class, 'showStep'])
        ->where('step', '[1-7]')
        ->name('step');

    // Procesa los datos enviados por GET desde el formulario y avanza de paso (Ej: /register/step/3/store)
    Route::get('/step/{step}/store', [RegisterWizardController::class, 'storeStep'])
        ->where('step', '[1-7]')
        ->name('step.store');
});

// ==========================================
// PANEL DE ADMINISTRACIÓN (DASHBOARD)
// ==========================================
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');
