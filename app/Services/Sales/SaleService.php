<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Exceptions\InvalidInvoiceStateException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gestión del carrito de venta. Su responsabilidad clave es el congelamiento de
 * datos maestros al agregar cada línea: description, unit_price,
 * unit_cost, is_taxable y, para compuestos, recipe_snapshot. Una vez congelados,
 * un cambio posterior en el producto o su receta no altera la venta.
 */
final class SaleService
{
    private const QTY_SCALE = 3;

    private const MONEY_SCALE = 2;

    public function __construct(
        private readonly SequenceGenerator $sequences,
    ) {
    }

    public function abrir(User $actor, int $branchId, int $customerId, ?string $tableReference, ?string $notes): Sale
    {
        return DB::transaction(function () use ($actor, $branchId, $customerId, $tableReference, $notes): Sale {
            // Folio interno de venta (no fiscal): usa el SequenceGenerator de MOD-03.
            $code = $this->sequences->next($actor->business_id, 'sale', 'V-');

            $sale = new Sale([
                'branch_id'       => $branchId,
                'customer_id'     => $customerId,
                'table_reference' => $tableReference,
                'notes'           => $notes,
                'opened_at'       => Carbon::now(),
            ]);
            $sale->code = $code;
            $sale->status = SaleStatus::Abierta;
            $sale->subtotal = '0.00';
            $sale->user_id = $actor->id;
            $sale->save();

            return $sale->refresh();
        });
    }

    // Agrega una línea congelando los datos maestros del producto vigente.
    public function agregarItem(Sale $sale, int $productId, string $quantity, string $discountAmount): SaleItem
    {
        return DB::transaction(function () use ($sale, $productId, $quantity, $discountAmount): SaleItem {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if (! $sale->status->canEditItems()) {
                throw InvalidInvoiceStateException::saleNotEditable($sale->id);
            }

            $product = Product::query()
                ->where('business_id', $sale->business_id)
                ->whereKey($productId)
                ->firstOrFail();

            // line_total = cantidad × precio − descuento (escala 2).
            $gross = bcmul($quantity, (string) $product->sale_price, self::MONEY_SCALE);
            $lineTotal = bcsub($gross, $discountAmount, self::MONEY_SCALE);
            if (bccomp($lineTotal, '0', self::MONEY_SCALE) < 0) {
                $lineTotal = '0.00';
            }

            $item = new SaleItem([
                'sale_id'         => $sale->id,
                'product_id'      => $product->id,
                'description'     => $product->name,                 // congelado
                'quantity'        => $quantity,
                'unit_price'      => (string) $product->sale_price,  // congelado
                'unit_cost'       => (string) $product->cost,        // congelado
                'is_taxable'      => $product->is_taxable,           // congelado (D-30)
                'discount_amount' => $discountAmount,
                'line_total'      => $lineTotal,
                'recipe_snapshot' => $this->freezeRecipe($product),  // congelado (H-59)
            ]);
            $item->save();

            $this->recalcularSubtotal($sale);

            return $item->refresh();
        });
    }

    public function quitarItem(Sale $sale, SaleItem $item): void
    {
        DB::transaction(function () use ($sale, $item): void {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if (! $sale->status->canEditItems()) {
                throw InvalidInvoiceStateException::saleNotEditable($sale->id);
            }

            $item->delete();

            $this->recalcularSubtotal($sale);
        });
    }

    public function confirmar(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($sale->status !== SaleStatus::Abierta) {
                throw InvalidInvoiceStateException::saleNotConfirmable($sale->id);
            }

            if (! $sale->items()->exists()) {
                throw InvalidInvoiceStateException::saleEmpty($sale->id);
            }

            $sale->status = SaleStatus::Confirmada;
            $sale->confirmed_at = Carbon::now();
            $sale->save();

            return $sale->refresh();
        });
    }

    /**
     * Congela la composición del compuesto (product_recipes) en un arreglo
     * simple. Para simples/servicios devuelve null (no hay receta). H-59.
     *
     * @return array<int, array{ingredient_id: int, quantity: string, unit_id: int}>|null
     */
    private function freezeRecipe(Product $product): ?array
    {
        if ($product->type !== ProductType::Compound) {
            return null;
        }

        return $product->recipeLines()
            ->get(['ingredient_id', 'quantity', 'unit_id'])
            ->map(static fn ($line): array => [
                'ingredient_id' => (int) $line->ingredient_id,
                'quantity'      => (string) $line->quantity,
                'unit_id'       => (int) $line->unit_id,
            ])
            ->all();
    }

    // Recalcula el subtotal de la venta como suma de line_total de sus ítems.
    private function recalcularSubtotal(Sale $sale): void
    {
        $subtotal = '0.00';

        foreach ($sale->items()->get(['line_total']) as $item) {
            $subtotal = bcadd($subtotal, (string) $item->line_total, self::MONEY_SCALE);
        }

        $sale->subtotal = $subtotal;
        $sale->save();
    }
}
