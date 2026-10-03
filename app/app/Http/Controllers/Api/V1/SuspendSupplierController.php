<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class SuspendSupplierController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SupplierService $suppliers,
    ) {}

    public function __invoke(Request $request, Supplier $supplier): SupplierResource
    {
        $this->authorize('suspend', $supplier); // BR-06: ROL-01

        // Limpia metadatos de aprobación (H-35). Devuelve el modelo 'suspendido'.
        $supplier = $this->suppliers->suspender($request->user(), $supplier);

        return new SupplierResource($supplier);
    }
}
