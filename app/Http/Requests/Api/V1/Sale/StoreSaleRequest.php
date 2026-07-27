<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Sale;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Sale;

/**
 * Abre el carrito. code y user_id de sistema/sesión. Nace 'abierta'.
 */
final class StoreSaleRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id'   => ['required', 'integer', $this->tenantExists('branches')],
            'customer_id' => ['required', 'integer', $this->tenantExists('customers')],
            'table_reference' => ['nullable', 'string', 'max:50'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required'   => 'La sucursal es obligatoria.',
            'branch_id.exists'     => 'La sucursal no existe o no pertenece a su negocio.',
            'customer_id.required' => 'El cliente es obligatorio.',
            'customer_id.exists'   => 'El cliente no existe o no pertenece a su negocio.',
            'table_reference.max'  => 'La referencia de mesa no puede exceder los 50 caracteres.',
            'notes.max'            => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id'       => 'sucursal',
            'customer_id'     => 'cliente',
            'table_reference' => 'referencia de mesa',
            'notes'           => 'observaciones',
        ];
    }
}
