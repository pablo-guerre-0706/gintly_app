<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaxRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxRule
 */
final class TaxRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'tax_class'        => $this->tax_class->value,
            'tax_class_label'  => $this->tax_class->label(),
            'fiscal_condition' => $this->tax_class->condition()->value,
            'branch_id'        => $this->branch_id,
            'scope'            => $this->branch_id === null ? 'business' : 'branch',
            'rate'             => $this->rate,          // decimal:6 → string
            'is_active'        => $this->is_active,
            'branch'           => new BranchResource($this->whenLoaded('branch')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
