<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;


// Direcciones de cliente.
final class CustomerAddressService
{
    public function crear(Customer $customer, array $data): CustomerAddress
    {
        // business_id lo inyecta el trait; customer_id se fija explícito.
        $address = new CustomerAddress($data);
        $address->customer_id = $customer->id;
        $address->save();

        return $address->refresh();
    }

    public function actualizar(CustomerAddress $address, array $data): CustomerAddress
    {
        $address->update($data);

        return $address->refresh();
    }

    // Borrado físico: Dirección subordinada del cliente y no tiene columna deleted_at.
    public function eliminar(CustomerAddress $address): void
    {
        $address->delete();
    }
}

