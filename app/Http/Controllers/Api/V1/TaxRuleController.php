<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaxClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TaxRule\IndexTaxRuleRequest;
use App\Http\Requests\Api\V1\TaxRule\StoreTaxRuleRequest;
use App\Http\Requests\Api\V1\TaxRule\UpdateTaxRuleRequest;
use App\Http\Resources\TaxRuleResource;
use App\Models\TaxRule;
use App\Services\Fiscal\TaxRuleService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Administración fiscal (ROL-01). Configura las tasas por clase fiscal y ámbito sin
 * tocar la base de datos a mano. Modelo VERSIONADO: un cambio de tasa preserva la
 * regla anterior y crea una nueva versión activa (TaxRuleService); las reglas usadas
 * por documentos históricos no se borran físicamente (destroy las DESACTIVA).
 */
final class TaxRuleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly TaxRuleService $taxRules)
    {
    }

    public function index(IndexTaxRuleRequest $request): AnonymousResourceCollection
    {
        $rules = TaxRule::query()
            ->with('branch')
            ->when(
                $request->validated('tax_class'),
                fn ($q, $class) => $q->where('tax_class', $class),
            )
            ->when(
                $request->has('branch_id'),
                fn ($q) => $q->where('branch_id', $request->integer('branch_id')),
            )
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active')),
            )
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return TaxRuleResource::collection($rules);
    }

    public function store(StoreTaxRuleRequest $request): JsonResponse
    {
        $this->authorize('create', TaxRule::class);

        $data = $request->validated();
        $rule = $this->guardActiveScope(fn (): TaxRule => $this->taxRules->crear(
            (int) $request->user()->business_id,
            TaxClass::from($data['tax_class']),
            isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            (string) $data['rate'],
        ));

        return TaxRuleResource::make($rule->load('branch'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Un cambio de tasa VERSIONA (nueva fila activa, la anterior se desactiva y
     * conserva); is_active se aplica en sitio. Devuelve la regla activa resultante con
     * 200 explícito: aunque el versionado cree una fila nueva (wasRecentlyCreated), el
     * verbo es una actualización, no una creación.
     */
    public function update(UpdateTaxRuleRequest $request, TaxRule $taxRule): JsonResponse
    {
        $this->authorize('update', $taxRule);

        $data = $request->validated();
        $rule = $taxRule;

        $rule = $this->guardActiveScope(function () use ($rule, $data): TaxRule {
            if (array_key_exists('rate', $data)) {
                $rule = $this->taxRules->actualizarTasa($rule, (string) $data['rate']);
            }
            if (array_key_exists('is_active', $data)) {
                $rule = $this->taxRules->setActivo($rule, (bool) $data['is_active']);
            }

            return $rule;
        });

        return TaxRuleResource::make($rule->refresh()->load('branch'))
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(TaxRule $taxRule): TaxRuleResource
    {
        $this->authorize('delete', $taxRule);

        // Baja LÓGICA fiscal: desactivar, nunca borrar (trazabilidad de históricos).
        $taxRule->update(['is_active' => false]);

        return TaxRuleResource::make($taxRule->refresh()->load('branch'));
    }

    /**
     * Traduce la violación del candado uniq_active_tax_rule_scope (1062) a un 422
     * legible: reactivar o crear una segunda regla activa para el mismo ámbito choca.
     */
    private function guardActiveScope(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062
                && str_contains($e->getMessage(), 'uniq_active_tax_rule_scope')
            ) {
                throw ValidationException::withMessages([
                    'tax_class' => ['Ya existe una regla fiscal activa para esta clase y ámbito.'],
                ]);
            }

            throw $e;
        }
    }
}
