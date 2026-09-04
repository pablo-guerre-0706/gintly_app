<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoicePaymentResource;
use App\Models\Invoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InvoicePaymentsController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Invoice $invoice): AnonymousResourceCollection
    {
        $this->authorize('view', $invoice);

        $payments = $invoice->payments()
            ->with(['user', 'cashSession'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return InvoicePaymentResource::collection($payments);
    }
}
