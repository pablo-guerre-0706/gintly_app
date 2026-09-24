<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * HTTP 422. No existe una regla fiscal aplicable (ni por sucursal ni general del
 * negocio) para una clase gravada. Rechazo CONTROLADO: la venta/facturación no
 * puede resolver la tasa y no debe inventarla ni caer en un 0.15 hardcodeado.
 *
 * Se auto-renderiza (self-render) con la clave `code`, coherente con el resto de
 * errores del contrato MOD-07; no se duplica en bootstrap/app.php.
 */
final class FiscalConfigurationMissingException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forClass(TaxClass $class, ?int $branchId): self
    {
        $scope = $branchId !== null ? "sucursal {$branchId}" : 'negocio';

        return new self(
            "No hay una regla fiscal activa para la clase «{$class->label()}» en la {$scope}. "
            .'Configure la tasa antes de vender productos de esta clase.'
        );
    }

    public static function forSaleItem(int $saleItemId): self
    {
        return new self(
            "La línea de venta {$saleItemId} no tiene fotografía fiscal congelada; "
            .'no puede facturarse sin una configuración fiscal resuelta.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'FISCAL_CONFIG_MISSING',
        ], 422);
    }
}
