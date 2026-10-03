<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Invoice\VoidInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class VoidInvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function __invoke(VoidInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('void', $invoice); // InvoicePolicy::void — ROL-01

        $invoice = $this->invoices->anular($invoice, $request->validated()['void_reason'], $request->user());

        return InvoiceResource::make($invoice->load(['customer', 'branch', 'sales']));
    }
}