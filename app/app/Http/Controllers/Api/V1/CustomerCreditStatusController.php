<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCreditStatusResource;
use App\Models\Customer;
use App\Services\Receivable\ReceivableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class CustomerCreditStatusController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    public function __invoke(Customer $customer): CustomerCreditStatusResource
    {
        $this->authorize('viewCreditStatus', $customer);

        return CustomerCreditStatusResource::make(
            $this->receivables->estadoDeCredito($customer),
        );
    }
}
