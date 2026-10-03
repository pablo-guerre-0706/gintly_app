<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceivablePaymentResource;
use App\Models\AccountReceivable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReceivablePaymentsController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, AccountReceivable $accountReceivable): AnonymousResourceCollection
    {
        $this->authorize('viewPayments', $accountReceivable);

        $payments = $accountReceivable->payments()
            ->with(['user', 'cashSession'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return ReceivablePaymentResource::collection($payments);
    }
}
