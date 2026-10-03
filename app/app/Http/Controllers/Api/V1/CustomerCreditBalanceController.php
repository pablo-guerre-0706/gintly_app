<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCreditBalanceResource;
use App\Models\Customer;
use App\Services\Returns\ReturnService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * MOD-10 · Saldo a favor del cliente (RF-10-03, solo lectura). En Fase 1 el saldo disponible
 * es la suma de las notas de crédito de tipo "saldo" vigentes (fuente de verdad). Capa delgada.
 */
final class CustomerCreditBalanceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReturnService $returns)
    {
    }

    /** GET /customers/{customer}/credit-balance */
    public function __invoke(Customer $customer): CustomerCreditBalanceResource
    {
        $this->authorize('viewCreditBalance', $customer);

        return new CustomerCreditBalanceResource($this->returns->saldoAFavor($customer));
    }
}
