<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/landing', function () {
    return view('landing');
})->name('landing');

// Paso 1: Registro de Empresa / Datos iniciales
Route::get('/register1', function () {
    return view('register1');
})->name('register.step1');

Route::post('/register1', function () {
    return redirect()->route('register.step2');
})->name('register.step1.store');

// Paso 2: Configuración de Perfil / Negocio
Route::get('/register2', function () {
    return view('register2');
})->name('register.step2');

Route::post('/register2', function () {
    return redirect()->route('dashboard');
})->name('register.step2.store');

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

// Inicio de sesión
Route::get('/login', function () {
    return view('auth.login');
})->name('login');