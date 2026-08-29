<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\ProtectedResourceException;
use App\Models\Customer;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class CustomerService
{
    public function crear(array $attributes): Customer
    {
        // business_id lo inyecta BelongsToBusiness; is_generic está fuera de $fillable.
        return $this->persistGuardingDocument(
            fn (): Customer => Customer::create($attributes),
        );
    }

    public function actualizar(Customer $customer, array $attributes): Customer
    {
        // Backstop D-17: cierra la vía no-HTTP (Jobs, consola, tinker).
        $this->assertNotProtected($customer);

        return $this->persistGuardingDocument(function () use ($customer, $attributes): Customer {
            $customer->update($attributes);

            return $customer->refresh();
        });
    }

    public function eliminar(Customer $customer): void
    {
        $this->assertNotProtected($customer);
        $this->assertHasNoLiveReceivables($customer);

        // Borrado lógico. Las direcciones quedan intactas para restauración nativa;
        // su borrado físico lo delega el cascadeOnDelete del motor durante forceDelete.
        $customer->delete();
    }

    private function assertNotProtected(Customer $customer): void
    {
        if ($customer->isProtected()) {
            throw new ProtectedResourceException();
        }
    }

    private function assertHasNoLiveReceivables(Customer $customer): void
    {
        if ($customer->hasPendingReceivables()) {
            throw new CustomerHasReceivablesException();
        }
    }

    /**
     * Defensa TOCTOU. Si dos operadores registran la misma cédula en la ventana
     * exacta entre la validación unique y el INSERT, el candado parcial compuesto
     * del motor (uniq_active_customer_document) dispara un 1062: lo interceptamos
     * y lo traducimos a un 422 nativo sobre document_number, jamás un 500 crudo.
     */
    private function persistGuardingDocument(Closure $operation): Customer
    {
        try {
            return $operation();
        } catch (QueryException $e) {
            if ($this->isDuplicateDocument($e)) {
                throw ValidationException::withMessages([
                    'document_number' => ['El número de documento ya está registrado para otro cliente activo del negocio.'],
                ]);
            }

            throw $e;
        }
    }

    private function isDuplicateDocument(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            && str_contains($e->getMessage(), 'uniq_active_customer_document');
    }
}