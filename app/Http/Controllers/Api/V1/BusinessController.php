<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Services\Fiscal\TaxRuleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BusinessController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly TaxRuleService $taxRules)
    {
    }

    /** El negocio se aprovisiona vía BusinessObserver; aquí solo se consulta/edita el propio (sin index/store/destroy). */
    public function show(Request $request): BusinessResource
    {
        $business = $request->user()->business;

        $this->authorize('view', $business);

        return new BusinessResource($business);
    }

    public function update(UpdateBusinessRequest $request): BusinessResource
    {
        $business = $request->user()->business;

        $this->authorize('update', $business);

        $data = $request->validated();

        // tax_rate se TRADUCE transaccionalmente a la regla ESTÁNDAR GENERAL (fuente
        // operativa única, versionada) para que editarla SÍ afecte la facturación; la
        // columna se sincroniza como espejo de compatibilidad. status/plan quedan fuera
        // de rules() (contrato SaaS, solo ROL-SYS).
        DB::transaction(function () use ($business, $data): void {
            if (array_key_exists('tax_rate', $data)) {
                $this->taxRules->fijarTasaEstandarGeneral((int) $business->id, (string) $data['tax_rate']);
            }

            $business->update($data); // incluye tax_rate: sincroniza la columna espejo
        });

        return new BusinessResource($business->refresh());
    }
}
