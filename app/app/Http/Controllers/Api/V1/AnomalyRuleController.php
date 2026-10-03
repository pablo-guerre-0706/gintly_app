<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Anomaly\UpdateAnomalyRuleRequest;
use App\Http\Resources\AnomalyRuleResource;
use App\Models\AnomalyRule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-11 · Reglas de anomalía (capa HTTP delgada). Catálogo CERRADO de 6 reglas por negocio
 * (sembradas por BusinessObserver). Solo se parametrizan umbral, severidad y activación (ROL-01);
 * code y name son inmutables (los protege $fillable del modelo).
 */
final class AnomalyRuleController extends Controller
{
    use AuthorizesRequests;

    /** GET /anomaly-rules — las reglas del negocio (catálogo fijo, sin paginar). */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnomalyRule::class);

        // BusinessScope acota al tenant; orden estable por código.
        $rules = AnomalyRule::query()->orderBy('code')->get();

        return AnomalyRuleResource::collection($rules);
    }

    /** PUT /anomaly-rules/{anomalyRule} — parametriza umbral/severidad/activación (ROL-01). */
    public function update(UpdateAnomalyRuleRequest $request, AnomalyRule $anomalyRule): AnomalyRuleResource
    {
        $this->authorize('update', $anomalyRule);

        // $fillable excluye code/name (inmutables); solo se persisten los atributos parametrizables.
        $anomalyRule->update($request->validated());

        return AnomalyRuleResource::make($anomalyRule->refresh());
    }
}
