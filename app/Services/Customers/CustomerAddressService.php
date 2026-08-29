<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;

final class CustomerAddressService
{
    public function crear(Customer $customer, array $attributes): CustomerAddress
    {
        // A través del titular: hereda customer_id y el business_id (auto-fill del trait).
        return $customer->addresses()->create($attributes);
    }

    public function actualizar(CustomerAddress $address, array $attributes): CustomerAddress
    {
        // $address llegó resuelto por scopeBindings() como hijo del {customer} de la ruta.
        $address->update($attributes);

        return $address->refresh();
    }

    public function eliminar(CustomerAddress $address): void
    {
        // Borrado FÍSICO: la tabla no tiene softDeletes (D-19).
        $address->delete();
    }
}