<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Api\V1\Customer\Concerns\ResolvesCustomerRoute;
use App\Models\CustomerAddress;
use Illuminate\Validation\Validator;

final class UpdateCustomerAddressRequest extends BaseTenantRequest
{
    use ResolvesCustomerRoute;

    public function authorize(): bool
    {
        $address = $this->address();

        return $address instanceof CustomerAddress
            && ($this->user()?->can('update', $address) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label'        => ['sometimes', 'required', 'string', 'max:50'],
            'address_line' => ['sometimes', 'required', 'string', 'max:255'],
            'reference'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_default'   => ['sometimes', 'boolean'],
        ];
    }

    /**
     * La dirección debe pertenecer al cliente de la ruta y coincidir con el cuerpo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->customerIsValid()) {
                    $validator->errors()->add(
                        'customer',
                        'El cliente indicado no existe o no pertenece a su negocio.'
                    );

                    return;
                }

                if (! $this->addressBelongsToCustomer()) {
                    $validator->errors()->add(
                        'address',
                        'La dirección indicada no pertenece a este cliente.'
                    );
                }

                // Candado de parentesco H-47: Evita que el body modifique el dueño de la dirección
                if (! $this->bodyCustomerMatchesRoute()) {
                    $validator->errors()->add(
                        'customer_id',
                        'El número de cliente enviado en el cuerpo no coincide con el cliente de la URL.'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required'        => 'La etiqueta de la dirección no puede quedar vacía.',
            'label.max'             => 'La etiqueta no puede exceder los 50 caracteres.',
            'address_line.required' => 'La dirección no puede quedar vacía.',
            'address_line.max'      => 'La dirección no puede exceder los 255 caracteres.',
            'reference.max'         => 'La referencia no puede exceder los 255 caracteres.',
            'is_default.boolean'    => 'El indicador de dirección predeterminada debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label'        => 'etiqueta',
            'address_line' => 'dirección',
            'reference'    => 'referencia',
            'is_default'   => 'dirección predeterminada',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->label)) {
            $merge['label'] = trim($this->label);
        }

        if (is_string($this->address_line)) {
            $merge['address_line'] = trim($this->address_line);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}