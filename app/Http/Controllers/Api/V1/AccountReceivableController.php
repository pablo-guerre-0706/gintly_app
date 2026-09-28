<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountReceivableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Receivables\IndexAccountReceivableRequest;
use App\Http\Resources\AccountReceivableResource;
use App\Http\Resources\CollectibleReceivableResource;
use App\Models\AccountReceivable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AccountReceivableController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexAccountReceivableRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccountReceivable::class);

        $receivables = AccountReceivable::query()
            ->with(['customer', 'invoice'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return AccountReceivableResource::collection($receivables);
    }

    /**
     * GET /accounts-receivable/collectible — CxC COBRABLES para la operación de cobro (Fase 7).
     * Solo estados pendiente/parcial/vencida. ROL-03 (cajero) acotado a SU sucursal. Búsqueda por
     * cliente, documento o folio de factura. Vista mínima (no abre el detalle administrativo).
     */
    public function collectible(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewCollectible', AccountReceivable::class);

        $user   = $request->user();
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $query = AccountReceivable::query()
            ->with(['customer', 'invoice'])
            ->whereIn('status', AccountReceivableStatus::exposureStatuses()); // pendiente/parcial/vencida.

        // ROL-03: solo CxC de facturas de SU sucursal.
        if ($user->isOperator()) {
            $query->whereHas('invoice', fn ($i) => $i->where('branch_id', $user->branch_id));
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search): void {
                $sub->whereHas('customer', fn ($c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%"))
                    ->orWhereHas('invoice', fn ($i) => $i->where('folio', 'like', "%{$search}%"));
            });
        }

        return CollectibleReceivableResource::collection(
            $query->orderBy('due_date')->paginate($perPage),
        );
    }

    public function show(AccountReceivable $accountReceivable): AccountReceivableResource
    {
        $this->authorize('view', $accountReceivable);

        return AccountReceivableResource::make(
            $accountReceivable->load(['customer', 'invoice']),
        );
    }
}
