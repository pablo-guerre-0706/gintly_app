<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\StockTransferItemResource;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransfer
 */
final class StockTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id'   => $this->to_warehouse_id,
            'user_id'           => $this->user_id,
            'status'            => $this->status->value,
            'status_label'      => $this->status->label(),
            'notes'             => $this->notes,
            'transferred_at'    => $this->transferred_at?->toIso8601String(),
            'from_warehouse'    => new WarehouseResource($this->whenLoaded('fromWarehouse')),
            'to_warehouse'      => new WarehouseResource($this->whenLoaded('toWarehouse')),
            // Líneas persistidas del traspaso (visibles incluso cuando está 'pendiente').
            'items'             => StockTransferItemResource::collection($this->whenLoaded('items')),
            'movements'         => InventoryMovementResource::collection($this->whenLoaded('movements')),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
