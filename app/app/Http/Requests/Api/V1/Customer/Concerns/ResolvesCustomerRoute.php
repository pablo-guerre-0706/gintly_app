<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Concerns;

use App\Models\Customer;
use App\Models\CustomerAddress;

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

    /**
     * Candado de parentesco H-47: si el body incluye customer_id,
     * debe coincidir milimétricamente con el {customer} de la URL.
     * Si el body no trae customer_id, no hay conflicto que validar.
     */
    protected function bodyCustomerMatchesRoute(): bool
    {
        if (! $this->has('customer_id')) {
            return true;
        }

        return $this->customerId() !== null
            && (int) $this->input('customer_id') === $this->customerId();
    }
}