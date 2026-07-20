<?php

namespace App\Services\Sales;

use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleService
{
    public function abrir(array $data): Sale
    {
        return Sale::create([
            'branch_id'       => $data['branch_id'],
            'customer_id'     => $data['customer_id'],
            'user_id'         => $data['user_id'],
            'code'            => 'V-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
            'table_reference' => $data['table_reference'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'opened_at'       => now(),
        ]);
    }

    /**
     * Agrega un ítem CONGELANDO nombre, precio, costo y —si es compuesto— la receta vigente
     * (RF-02-05). El congelamiento es lo que hace que editar el catálogo NO altere ventas pasadas.
     */
    public function agregarItem(Sale $sale, int $productId, string $quantity, string $discount = '0.00'): SaleItem
    {
        if (! $sale->status->canEditItems()) {
            throw new DomainException('Solo una venta abierta admite cambios de ítems.');
        }

        $product = Product::query()->findOrFail($productId);

        // Congelamiento de receta: explota la composición vigente en un snapshot inmutable.
        $snapshot = null;
        if ($product->type === ProductType::Compound) {
            $snapshot = $product->recipeItems()->get()->map(fn ($r): array => [
                'ingredient_id' => $r->ingredient_id,
                'quantity'      => (string) $r->quantity,   // por 1 unidad del compuesto
                'unit_id'       => $r->unit_id,
            ])->all();
        }

        $item = DB::transaction(function () use ($sale, $product, $quantity, $discount, $snapshot) {
            $item = $sale->items()->create([
                'product_id'      => $product->id,
                'description'     => $product->name,            // nombre congelado
                'quantity'        => $quantity,
                'unit_price'      => (string) $product->sale_price,  // precio congelado
                'unit_cost'       => (string) $product->cost,        // costo congelado
                'discount_amount' => $discount,
                'recipe_snapshot' => $snapshot,
                // line_total → booted del modelo
            ]);
            $this->recalcularSubtotal($sale);
            return $item;
        });

        return $item;
    }

    public function confirmar(Sale $sale): Sale
    {
        if ($sale->status !== SaleStatus::Abierta) {
            throw new DomainException('Solo una venta abierta puede confirmarse.');
        }
        if ($sale->items()->count() === 0) {
            throw new DomainException('No se puede confirmar una venta sin ítems.');
        }

        $sale->status       = SaleStatus::Confirmada;
        $sale->confirmed_at = now();
        $sale->save();

        return $sale;
    }

    private function recalcularSubtotal(Sale $sale): void
    {
        $subtotal = '0.00';
        foreach ($sale->items()->get() as $item) {
            $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
        }
        $sale->subtotal = $subtotal;
        $sale->save();
    }
}
