<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
final class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'cash_session_id'     => $this->cash_session_id,
            'user_id'             => $this->user_id,
            'type'                => $this->type->value,
            'type_label'          => $this->type->label(),
            'category'            => $this->category->value,
            'category_label'      => $this->category->label(),
            'payment_method'      => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'affects_cash_drawer' => $this->payment_method->affectsCashDrawer(),
            'amount'              => $this->amount,
            'authorized_by'       => $this->authorized_by,
            'description'         => $this->description,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
