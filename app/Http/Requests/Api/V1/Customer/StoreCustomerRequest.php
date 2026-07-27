<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\DocumentType;
use App\Http\Requests\BaseTenantRequest;
use App\Models\Customer;
use Illuminate\Validation\Rule;


final class StoreCustomerRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],

            'document_type' => ['required', Rule::in(DocumentType::publicValues())],

            'document_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('customers', 'document_number')
                    ->where('business_id', $this->businessId())
                    ->whereNull('deleted_at'),
            ],

            'email'        => ['nullable', 'string', 'email:rfc', 'max:180'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'birth_date'   => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],

            // credit_limit escala 2. Se registra aquí. Su aplicación en MOD-08.
            'credit_limit' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'          => 'El nombre del cliente es obligatorio.',
            'name.max'               => 'El nombre no puede exceder los 160 caracteres.',
            'document_type.required' => 'El tipo de documento es obligatorio.',
            'document_type.in'       => 'El tipo de documento debe ser cédula, RUC o pasaporte.',
            'document_number.max'    => 'El número de documento no puede exceder los 30 caracteres.',
            'document_number.unique' => 'Ya existe un cliente activo con este número de documento en el negocio.',
            'email.email'            => 'El correo electrónico no tiene un formato válido.',
            'email.max'              => 'El correo electrónico no puede exceder los 180 caracteres.',
            'phone_number.max'       => 'El teléfono no puede exceder los 30 caracteres.',
            'birth_date.date_format' => 'La fecha de nacimiento debe expresarse en formato AAAA-MM-DD.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser posterior al día de hoy.',
            'credit_limit.numeric'   => 'El límite de crédito debe ser un valor numérico.',
            'credit_limit.decimal'   => 'El límite de crédito admite un máximo de dos decimales.',
            'credit_limit.min'       => 'El límite de crédito no puede ser negativo.',
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

        // document_number en blanco → null: un vacío rompería el candado
        // (dos vacíos colisionarían); null admite múltiples.
        if (is_string($this->document_number)) {
            $trimmed = trim($this->document_number);
            $merge['document_number'] = $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
