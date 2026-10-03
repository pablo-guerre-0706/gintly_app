<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use App\Services\Purchasing\AccountPayableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class UnblockAccountPayableController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AccountPayableService $payables,
    ) {}

    public function __invoke(Request $request, AccountPayable $accountPayable): AccountPayableResource
    {
        $this->authorize('unblock', $accountPayable); // ROL-01

        // congelada -> pendiente/parcial según paid_amount. InvalidPurchaseStateException::payableNotBlocked (409) si no está congelada.
        $payable = $this->payables->descongelar($request->user(), $accountPayable);

        return new AccountPayableResource($payable->load(['supplier', 'purchaseOrder', 'goodsReceipt']));
    }
}
