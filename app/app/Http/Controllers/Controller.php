<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

/**
 * Base de todos los controladores. Extiende Illuminate\Routing\Controller para
 * restaurar el registro de middleware de controlador (`middleware()` / `getMiddleware()`),
 * que Laravel 11 retiró del stub por defecto. Sin esto, cualquier controlador que use
 * AuthorizesRequests::authorizeResource() (Product, Category, Brand, Unit, Warehouse,
 * Branch, User) lanzaba "Call to undefined method middleware()" (500) en cada escritura
 * HTTP; con esto la autorización por recurso se registra y SE APLICA realmente.
 */
abstract class Controller extends BaseController
{
    // Código común a todos los controladores, si se necesita.
}
