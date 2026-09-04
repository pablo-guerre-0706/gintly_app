<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class ConfirmSaleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly SaleService $sales)
    {
    }

    public function __invoke(Request $request, Sale $sale): SaleResource
    {
        $this->authorize('confirm', $sale);

        $sale = $this->sales->confirmar($sale, $request->user());

        return SaleResource::make($sale->load(['customer', 'branch', 'user', 'items']));
    }
}
