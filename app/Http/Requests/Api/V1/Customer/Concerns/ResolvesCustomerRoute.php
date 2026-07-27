<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Concerns;

use App\Models\Customer;
use App\Models\CustomerAddress;


// Resolución del binding anidado /customers/{customer}/addresses/{address} garantiza
// que la dirección pertenezca al cliente de la ruta, no a otro.
trait ResolvesCustomerRoute
{
    protected function customer(): ?Customer
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer ? $customer : null;
    }

    protected function customerId(): ?int
    {
        return $this->customer()?->getKey();
    }

    protected function address(): ?CustomerAddress
    {
        $address = $this->route('address');

        return $address instanceof CustomerAddress ? $address : null;
    }

    protected function customerIsValid(): bool
    {
        $customer = $this->customer();

        return $customer !== null
            && (int) $customer->business_id === $this->businessId();
    }

    protected function addressBelongsToCustomer(): bool
    {
        $address = $this->address();

        return $address !== null
            && $this->customerId() !== null
            && (int) $address->customer_id === $this->customerId();
    }
}
