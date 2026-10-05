<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExchangeRate\StoreExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Services\Cash\ExchangeRateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * MOD-06 · Administración del tipo de cambio (ROL-01/ROL-02). Capa HTTP delgada: valida en
 * StoreExchangeRateRequest, autoriza por ExchangeRatePolicy y delega el registro a ExchangeRateService.
 * El historial es inmutable y versionado por vigencia (append-only): solo index + store.
 */
final class ExchangeRateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    /** GET /exchange-rates — historial de vigencias (más reciente primero); filtro opcional ?currency=. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $currency = Currency::tryFrom((string) $request->query('currency', ''));

        $rates = ExchangeRate::query()
            ->with('createdBy')
            ->when($currency !== null, fn ($q) => $q->where('currency', $currency->value))
            ->orderBy('currency')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return ExchangeRateResource::collection($rates);
    }

    /** POST /exchange-rates — registra una nueva vigencia (ROL-01/ROL-02). */
    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        // Autorización resuelta en StoreExchangeRateRequest::authorize() (ExchangeRatePolicy::create).
        $actor = $request->user();

        $rate = $this->exchangeRates->register(
            (int) $actor->business_id,
            Currency::from($request->validated('currency')),
            (string) $request->validated('rate'),
            Carbon::parse($request->validated('effective_from')),
            (int) $actor->id,
        );

        return ExchangeRateResource::make($rate->load('createdBy'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
