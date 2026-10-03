<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EligibleInvoiceResource;
use App\Http\Resources\EligibleReturnLineResource;
use App\Models\SalesReturn;
use App\Services\Returns\ReturnEligibilityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-10 · Lectura mínima y autorizada para PREPARAR devoluciones. Permite al BODEGUERO
 * (capacidad devoluciones.crear, SIN facturas.ver) descubrir las facturas y líneas devolvibles de SU
 * sucursal, sin acceso general a /invoices. Autoriza con la MISMA capacidad del alta (SalesReturnPolicy::create).
 */
final class SalesReturnEligibilityController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReturnEligibilityService $eligibility) {}

    /** GET /sales-returns/eligible-invoices — facturas con líneas devolvibles de la sucursal del usuario. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', SalesReturn::class); // exige devoluciones.crear (ROL-03) o ROL-01/02.

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        return EligibleInvoiceResource::collection(
            $this->eligibility->invoices($request->user(), $perPage),
        );
    }

    /** GET /sales-returns/eligible-invoices/{invoice}/items — líneas devolvibles de una factura elegible. */
    public function items(Request $request, int $invoice): AnonymousResourceCollection
    {
        $this->authorize('create', SalesReturn::class);

        // Resuelve dentro del alcance del actor (sucursal + no anulada); 404 si es ajena (no revela existencia).
        $eligible = $this->eligibility->eligibleInvoiceOrFail($request->user(), $invoice);

        return EligibleReturnLineResource::collection(
            $this->eligibility->returnableItems($eligible),
        );
    }
}
