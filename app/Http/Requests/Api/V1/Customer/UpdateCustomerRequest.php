<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\DocumentType;
use App\Http\Requests\BaseTenantRequest;
use App\Models\Customer;
use Illuminate\Validation\Rule;


// La protección del Consumidor Final la resuelve la Policy (update deniega si isProtected).
final class UpdateCustomerRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('customer');

        return $this->user()?->can('update', $target instanceof Customer ? $target : Customer::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],

            'document_type' => ['sometimes', 'required', Rule::in(DocumentType::publicValues())],

            'document_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('customers', 'document_number')
                    ->where('business_id', $this->businessId())
                    ->whereNull('deleted_at')
                    ->ignore($this->routeId('customer')),
            ],

            'email'        => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:180'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_date'   => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'credit_limit' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],
            'is_active'    => ['sometimes', 'boolean'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'          => 'El nombre del cliente no puede quedar vacío.',
            'name.max'               => 'El nombre no puede exceder los 160 caracteres.',
            'document_type.in'       => 'El tipo de documento debe ser cédula, RUC o pasaporte.',
            'document_number.max'    => 'El número de documento no puede exceder los 30 caracteres.',
            'document_number.unique' => 'Ya existe otro cliente activo con este número de documento en el negocio.',
            'email.email'            => 'El correo electrónico no tiene un formato válido.',
            'email.max'              => 'El correo electrónico no puede exceder los 180 caracteres.',
            'phone_number.max'       => 'El teléfono no puede exceder los 30 caracteres.',
            'birth_date.date_format' => 'La fecha de nacimiento debe expresarse en formato AAAA-MM-DD.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser posterior al día de hoy.',
            'credit_limit.numeric'   => 'El límite de crédito debe ser un valor numérico.',
            'credit_limit.decimal'   => 'El límite de crédito admite un máximo de dos decimales.',
            'credit_limit.min'       => 'El límite de crédito no puede ser negativo.',
            'is_active.boolean'      => 'El estado del cliente debe ser verdadero o falso.',
            'notes.max'              => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'            => 'nombre',
            'document_type'   => 'tipo de documento',
            'document_number' => 'número de documento',
            'email'           => 'correo electrónico',
            'phone_number'    => 'teléfono',
            'birth_date'      => 'fecha de nacimiento',
            'credit_limit'    => 'límite de crédito',
            'is_active'       => 'estado',
            'notes'           => 'observaciones',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->name)) {
            $merge['name'] = trim($this->name);
        }

        if (is_string($this->email)) {
            $merge['email'] = mb_strtolower(trim($this->email));
        }

        if (is_string($this->document_number)) {
            $trimmed = trim($this->document_number);
            $merge['document_number'] = $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
