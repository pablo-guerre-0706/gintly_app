<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class CancelStockTransferController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly StockTransferService $transfers,
    ) {}

    public function __invoke(Request $request, StockTransfer $stockTransfer): StockTransferResource
    {
        $this->authorize('cancel', $stockTransfer);

        // Solo cancelable en 'pendiente' (canTransition). Devuelve el modelo 'cancelado'.
        $transfer = $this->transfers->cancelar($request->user(), $stockTransfer);

        return new StockTransferResource($transfer->load(['fromWarehouse', 'toWarehouse', 'user']));
    }
}
