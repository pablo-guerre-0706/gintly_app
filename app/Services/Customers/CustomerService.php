<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\ProtectedResourceException;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;


final class CustomerService
{
    public function crear(array $data): Customer
    {
        // is_generic no está en $fillable → nace false por default de columna.
        return Customer::query()->create($data);
    }

    public function actualizar(Customer $customer, array $data): Customer
    {
        // Backstop del service además de la Policy: defensa en profundidad.
        if ($customer->isProtected()) {
            throw ProtectedResourceException::customer();
        }

        $customer->update($data);

        return $customer->refresh();
    }

    // Soft-delete. Conserva el historial de compras.
    public function eliminar(Customer $customer): void
    {
        if ($customer->isProtected()) {
            throw ProtectedResourceException::customer();
        }

        if ($customer->hasPendingReceivables()) {
            throw CustomerHasReceivablesException::make($customer->id);
        }

        DB::transaction(function () use ($customer): void {
            $customer->delete();
        });
    }
}
