<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockTransfer\CompleteStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class CompleteStockTransferController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly StockTransferService $transfers,
    ) {}

    public function __invoke(CompleteStockTransferRequest $request, StockTransfer $stockTransfer): StockTransferResource
    {
        $this->authorize('complete', $stockTransfer);

        // DM3-01 paso 2: las líneas ($items) viajan aquí. InsufficientStockException (409) si falta stock.
        $transfer = $this->transfers->completar(
            $request->user(),
            $stockTransfer,
            $request->validated('items'),
        );

        return new StockTransferResource($transfer->load(['fromWarehouse', 'toWarehouse', 'user']));
    }
}
