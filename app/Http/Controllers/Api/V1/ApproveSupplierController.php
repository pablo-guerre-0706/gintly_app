<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class ApproveSupplierController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SupplierService $suppliers,
    ) {}

    public function __invoke(Request $request, Supplier $supplier): SupplierResource
    {
        $this->authorize('approve', $supplier); // ROL-01

        // Puebla approved_by/approved_at (coherencia estructural H-35). Devuelve el modelo 'aprobado'.
        $supplier = $this->suppliers->aprobar($request->user(), $supplier);

        return new SupplierResource($supplier);
    }
}
