<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\GoodsReceipt;

use App\Http\Requests\BaseTenantRequest;
use App\Models\GoodsReceipt;
use Illuminate\Validation\Rule;


// Resolución de discrepancia por ROL-01.
final class ResolveGoodsReceiptRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('goods_receipt');

        return $target instanceof GoodsReceipt
            && ($this->user()?->can('resolve', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['aceptar', 'rechazar'])],
            'notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution.required' => 'Debe indicar la resolución (aceptar o rechazar).',
            'resolution.in'       => 'La resolución solo admite los valores "aceptar" o "rechazar".',
            'notes.max'           => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resolution' => 'resolución',
            'notes'      => 'observaciones',
        ];
    }
}
