<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Unit;

use App\Http\Requests\BaseTenantRequest;
use App\Models\UnitOfMeasure;


// Catálogo pequeño, devuelve colección completa, sin paginar (contrato), solo un filtro de búsqueda opcional.
final class IndexUnitRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', UnitOfMeasure::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.min' => 'El término de búsqueda no puede estar vacío.',
            'search.max' => 'El término de búsqueda no puede exceder los 50 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['search' => 'término de búsqueda'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->search)) {
            $this->merge(['search' => trim($this->search)]);
        }
    }
}

