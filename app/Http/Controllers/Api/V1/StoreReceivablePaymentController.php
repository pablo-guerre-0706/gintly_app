<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Receivables\StoreReceivablePaymentRequest;
use App\Http\Resources\ReceivablePaymentResource;
use App\Models\AccountReceivable;
use App\Services\Receivable\ReceivableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class StoreReceivablePaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    public function __invoke(
        StoreReceivablePaymentRequest $request,
        AccountReceivable $accountReceivable,
    ): JsonResponse {
        $this->authorize('pay', $accountReceivable);

        // abonar() aplica lock, valida saldo, fija user_id (no-repudio) y maneja caja.
        // Ya retorna el pago con ['accountReceivable', 'user'] cargados (fresh).
        $payment = $this->receivables->abonar($accountReceivable, $request->validated());

        return ReceivablePaymentResource::make($payment)
            ->response()
            ->setStatusCode(201);
    }
}
