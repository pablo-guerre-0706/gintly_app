<?php

use Illuminate\Support\Facades\Route;


// Web Routes - ERP Gintly App //

// 1. La Landing Page oficial
Route::get('/', function () {
    return view('landing'); // Carga resources/views/landing.blade.php
})->name('landing');

// 2. Rutas temporales del asistente de registro, view:perfil de usuario
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

// 3. Rutas temporales de envío, simuladas para evitar errores al presionar botones, al enviar paso 1 salta al paso 2
Route::post('/register/step1', function () {
    return redirect()->route('register.step2');
})->name('register.step1.store');

Route::post('/register/step2', function () {
    return response()->json(['message' => 'Simulación de registro exitosa']);
})->name('register.step2.store');

// 4. Ruta temporal de inicio de sesión (lo requieren los botones de la Landing)
Route::get('/login', function () {
    return "Pantalla de Login (Pendiente de enlazar al backend)";
})->name('login');
