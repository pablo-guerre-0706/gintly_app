<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Invoice;

use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Invoice;
use Illuminate\Validation\Rule;

final class IndexInvoiceRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Invoice::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['folio', 'total', 'issued_at', 'payment_status', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), $this->dateRangeRules(), [
            'status'         => ['sometimes', Rule::enum(InvoiceStatus::class)],
            'payment_type'   => ['sometimes', Rule::enum(InvoicePaymentType::class)],
            'payment_status' => ['sometimes', Rule::enum(InvoicePaymentStatus::class)],
            'customer_id'    => ['sometimes', 'integer', $this->tenantExists('customers')],
            'folio'          => ['sometimes', 'string', 'max:30'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), $this->dateRangeMessages(), [
            'status.enum'         => 'El estado indicado no es válido.',
            'payment_type.enum'   => 'El tipo de pago indicado no es válido.',
            'payment_status.enum' => 'El estado de cobro indicado no es válido.',
            'customer_id.exists'  => 'El cliente indicado no existe o no pertenece a su negocio.',
            'folio.max'           => 'El folio no puede exceder los 30 caracteres.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), $this->dateRangeAttributes(), [
            'status'         => 'estado',
            'payment_type'   => 'tipo de pago',
            'payment_status' => 'estado de cobro',
            'customer_id'    => 'cliente',
            'folio'          => 'folio',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
