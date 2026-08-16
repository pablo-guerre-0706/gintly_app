<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AccountPayable\PayAccountPayableRequest;
use App\Http\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use App\Services\Purchasing\AccountPayableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class PayAccountPayableController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AccountPayableService $payables,
    ) {}

    public function __invoke(PayAccountPayableRequest $request, AccountPayable $accountPayable): AccountPayableResource
    {
        // amount = abono actual (decimal:0,2, gt:0). El Service suma bajo lock: 409 si congelada, 422 si excede el saldo.
        $payable = $this->payables->pagar(
            $accountPayable,
            (string) $request->validated('amount'),
            $request->validated('due_date')
        );

        return new AccountPayableResource($payable->load(['supplier', 'purchaseOrder', 'goodsReceipt']));
    }
}
