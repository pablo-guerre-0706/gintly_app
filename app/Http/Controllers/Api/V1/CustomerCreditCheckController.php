<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Receivables\CreditCheckRequest;
use App\Http\Resources\CreditCheckResource;
use App\Models\Customer;
use App\Services\Receivable\ReceivableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class CustomerCreditCheckController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    public function __invoke(CreditCheckRequest $request, Customer $customer): CreditCheckResource
    {
        $this->authorize('checkCredit', $customer);

        // (string) explícito: evaluarCredito() exige string y el servicio corre con strict_types=1.
        $result = $this->receivables->evaluarCredito(
            $customer,
            (string) $request->validated()['amount'],
        );

        return CreditCheckResource::make($result);
    }
}
