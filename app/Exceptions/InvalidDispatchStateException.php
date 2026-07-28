<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidDispatchStateException extends RuntimeException
{
    private int $status;

    public function __construct(string $message, int $status = 409)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    /** El retiro ya no está 'registrado' (RF-09-04). */
    public static function alreadyReverted(): self
    {
        return new self('El retiro ya fue revertido; no admite una nueva reversión.', 409);
    }

    /** La línea no pertenece a la factura del retiro. */
    public static function lineNotOnInvoice(int $saleItemId): self
    {
        return new self("La línea de venta #{$saleItemId} no pertenece a la factura indicada.", 422);
    }

    /** El producto de la línea es un servicio: no despacha. */
    public static function serviceNotDispatchable(int $saleItemId): self
    {
        return new self("La línea de venta #{$saleItemId} es un servicio y no genera retiro.", 422);
    }

    /** La sucursal emisora no tiene bodega predeterminada. */
    public static function noDefaultWarehouse(int $branchId): self
    {
        return new self("La sucursal #{$branchId} no tiene una bodega predeterminada configurada.", 409);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'INVALID_DISPATCH_STATE',
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
