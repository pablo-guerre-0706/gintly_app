<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


// Coherencia estructural del proveedor.
final class SupplierService
{
    public function crear(User $actor, array $data): Supplier
    {
        // status='pendiente' por default de columna; no se acepta del request.
        return Supplier::query()->create($data);
    }

    public function actualizar(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->refresh();
    }


    // Aprobación por ROL-01. Coherencia estructural: registra quién y cuándo aprobo.
    public function aprobar(User $actor, Supplier $supplier): Supplier
    {
        return DB::transaction(function () use ($actor, $supplier): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();

            $supplier->status = SupplierStatus::Aprobado;
            $supplier->approved_by = $actor->id;
            $supplier->approved_at = Carbon::now();
            $supplier->save();

            return $supplier->refresh();
        });
    }

    // Suspensión por ROL-01. Limpia los metadatos de aprobación
    public function suspender(User $actor, Supplier $supplier): Supplier
    {
        return DB::transaction(function () use ($supplier): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();

            $supplier->status = SupplierStatus::Suspendido;
            $supplier->approved_by = null;
            $supplier->approved_at = null;
            $supplier->save();

            return $supplier->refresh();
        });
    }
}
