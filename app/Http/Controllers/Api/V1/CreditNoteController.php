<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesReturn\IndexCreditNoteRequest;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-10 · Notas de crédito (solo lectura). Las NC se emiten dentro de ReturnService al
 * procesar la devolución; aquí solo se listan y consultan. Capa HTTP delgada.
 */
final class CreditNoteController extends Controller
{
    use AuthorizesRequests;

    /** GET /credit-notes — notas de crédito del negocio, filtradas y paginadas. */
    public function index(IndexCreditNoteRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CreditNote::class);

        $creditNotes = CreditNote::query()
            ->with(['invoice', 'customer', 'salesReturn', 'resolutions']) // Evita N+1.
            ->when(
                $request->filled('invoice_id'),
                fn ($query) => $query->where('invoice_id', $request->integer('invoice_id')),
            )
            ->when(
                $request->filled('customer_id'),
                fn ($query) => $query->where('customer_id', $request->integer('customer_id')),
            )
            ->when(
                $request->filled('resolution_type'),
                fn ($query) => $query->where('resolution_type', $request->string('resolution_type')),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->orderBy($request->sortColumn('id'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return CreditNoteResource::collection($creditNotes);
    }

    /** GET /credit-notes/{creditNote} — detalle de la nota de crédito. */
    public function show(CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('view', $creditNote);

        return CreditNoteResource::make($creditNote->load('resolutions'));
    }
}
