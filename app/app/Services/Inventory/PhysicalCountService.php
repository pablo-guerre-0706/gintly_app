<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\PhysicalCountStatus;
use App\Models\PhysicalCount;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


// Registro de conteos.
final class PhysicalCountService
{
    public function registrar(User $actor, int $warehouseId, int $productId, string $countedQuantity, ?string $notes): PhysicalCount
    {
        return DB::transaction(function () use ($actor, $warehouseId, $productId, $countedQuantity, $notes): PhysicalCount {
            // system_quantity = saldo físico actual. Si el par no tiene saldo, es 0.
            $systemQuantity = StockLevel::query()
                ->where('business_id', $actor->business_id)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->value('quantity') ?? '0.000';

            $count = new PhysicalCount();
            $count->business_id = $actor->business_id;
            $count->product_id = $productId;
            $count->warehouse_id = $warehouseId;
            $count->user_id = $actor->id; // derivado de sesión (D-7)
            $count->system_quantity = (string) $systemQuantity;
            $count->counted_quantity = $countedQuantity;
            // difference la calcula el motor (columna generada).
            $count->status = PhysicalCountStatus::Abierto;
            $count->notes = $notes;
            $count->counted_at = Carbon::now();
            $count->save();

            return $count->refresh();
        });
    }
}

