<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class StoreInvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function __invoke(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoices->facturar($request->validated(), $request->user());

        return InvoiceResource::make($invoice)
            ->response()
            ->setStatusCode(201);
    }
}
