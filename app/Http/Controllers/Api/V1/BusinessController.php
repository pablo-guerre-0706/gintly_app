<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class BusinessController extends Controller
{
    use AuthorizesRequests;

    /** El negocio se aprovisiona vía BusinessObserver; aquí solo se consulta/edita el propio (sin index/store/destroy). */
    public function show(Request $request): BusinessResource
    {
        $business = $request->user()->business;

        $this->authorize('view', $business);

        return new BusinessResource($business);
    }

    public function update(UpdateBusinessRequest $request): BusinessResource
    {
        $business = $request->user()->business;

        $this->authorize('update', $business);

        // status/plan quedan fuera de rules() (contrato SaaS, solo ROL-SYS) -> update() no puede tocarlos.
        $business->update($request->validated());

        return new BusinessResource($business);
    }
}
