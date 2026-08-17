<?php

use Illuminate\Support\Facades\Route;

// 1. Pagina de bienvenida que trae Laravel por defecto
Route::get('/', function () {
    return view('welcome');
});

// 2. MOD-02: Catálogo y Datos Maestros
Route::get('/catalogo', function () {
    return view('catalogo'); // Carga resources/views/catalogo.blade.php
});

// 3. MOD-03: Inventario Lógico y Bodega Física
Route::get('/inventario', function () {
    return view('inventario'); // Carga resources/views/inventario.blade.php
});

// 4. Pantalla Principal: Dashboard
Route::get('/dashboard', function () {
    return view('dashboard'); // Carga resources/views/dashboard.blade.php
});
