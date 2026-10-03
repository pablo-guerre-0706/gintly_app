<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DocumentType;
use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalySeverity;
use App\Enums\AnomalyThresholdType;
use App\Enums\TaxClass;
use App\Models\AnomalyRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\TaxRule;
use Illuminate\Support\Facades\DB;



/**
 * Aprovisionamiento del negocio (RF-01-06): siembra el cliente genérico, las
 * secuencias de folios, el catálogo de reglas de anomalía y la matriz de roles.
 *
 * DOCUMENTACIÓN DEL MECANISMO (idempotencia race-safe):
 *  - Cada siembra usa createOrFirst (INSERT-first; ante colisión con el índice
 *    UNIQUE del motor relee la fila), NO exists()+insert (que no es atómico).
 *  - business_id, is_generic, code, name y threshold_type NO son fillable y NO se
 *    añaden a $fillable. El bypass de mass-assignment proviene EXCLUSIVAMENTE del
 *    contexto Model::unguarded(...) acotado alrededor de cada createOrFirst.
 *    IMPORTANTE: el primer argumento (atributos de búsqueda) de firstOrCreate/
 *    createOrFirst NO evita por sí mismo la protección de asignación masiva; sin el
 *    contexto unguarded esas columnas se descartarían del INSERT.
 *  - Respaldo del motor por siembra: customers → uniq_generic_customer_per_business
 *    (columna generada generic_lock); document_sequences → uniq_business_document_type;
 *    anomaly_rules → uniq_anomaly_rule_code.
 */
final class BusinessObserver
{
    // Se dispara automaticamente despues que un negocio se guarda en la BD.
    public function created(Business $business): void
    {
        DB::transaction(function () use ($business): void {
            $this->seedGenericCustomer($business);
            $this->seedDocumentSequences($business);
            $this->seedAnomalyRules($business);
            $this->seedTaxConfiguration($business);

            app(\Database\Seeders\RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);
        });
    }

    /**
     * Configuración fiscal inicial: una regla ESTÁNDAR general activa con la tasa por
     * defecto del negocio (business.tax_rate). Es el equivalente 1:1 al comportamiento
     * previo; el resto de clases (reducida/tasa cero/exento) no requieren regla para
     * vender —tasa cero y exento resuelven 0; reducida se configura vía API si se usa—.
     * Race-safe: respaldado por uniq_active_tax_rule_scope. bypass acotado a unguarded.
     */
    private function seedTaxConfiguration(Business $business): void
    {
        // Aprovisionamiento EXPLÍCITO: solo si el negocio declaró una tasa se funda su
        // regla estándar general (fuente operativa única). Sin tasa explícita NO se
        // inventa ninguna regla: el negocio existe y arma catálogo, pero vender un
        // producto gravado sin regla aplicable → 422 FISCAL_CONFIG_MISSING.
        if ($business->tax_rate === null) {
            return;
        }

        TaxRule::unguarded(fn () => TaxRule::query()->createOrFirst(
            [
                'business_id' => $business->id,
                'tax_class'   => TaxClass::Standard->value,
                'branch_id'   => null,
                'is_active'   => true,
            ],
            ['rate' => (string) $business->tax_rate],
        ));
    }

    // Cliente genérico "Consumidor Final": uno por negocio, protegido.
    private function seedGenericCustomer(Business $business): void
    {
        // Race-safe: createOrFirst intenta el INSERT y, ante colisión con
        // uniq_generic_customer_per_business (índice único sobre la columna generada
        // generic_lock = CASE WHEN is_generic=1 THEN business_id END), relee la fila
        // existente. NO es exists()+insert (no atómico). El bypass de mass-assignment
        // proviene del contexto unguarded ACOTADO alrededor del createOrFirst: el
        // primer argumento de createOrFirst NO evita la protección por sí mismo.
        Customer::unguarded(fn () => Customer::query()->createOrFirst(
            ['business_id' => $business->id, 'is_generic' => true],
            [
                'name'            => 'Consumidor Final',
                'document_type'   => DocumentType::Generico,
                'document_number' => null,
                'is_active'       => true,
            ]
        ));
    }

    // Secuencia de documentos por defecto para el negocio
    private function seedDocumentSequences(Business $business): void
    {
        if (! class_exists(DocumentSequence::class)) {
            return;
        }

        $sequences = [
            'invoice'     => 'F-',
            'credit_note' => 'NC-',
            'sale'        => 'V-',
            'sales_return' => 'DV-',
        ];

        foreach ($sequences as $documentType => $prefix) {
            // Race-safe, respaldado por uniq_business_document_type. bypass acotado.
            DocumentSequence::unguarded(fn () => DocumentSequence::query()->createOrFirst(
                ['business_id' => $business->id, 'document_type' => $documentType],
                ['prefix' => $prefix, 'next_number' => 1]
            ));
        }
    }

    private function seedAnomalyRules(Business $business): void
    {
        // Catálogo cerrado de 6 reglas con umbral/severidad por defecto. firstOrCreate ⇒ idempotente.
        $rules = [
            [AnomalyRuleCode::DescuadreCaja,      'Descuadre de caja',           AnomalyThresholdType::Monto,    AnomalySeverity::Advertencia, null],
            [AnomalyRuleCode::FaltanteInventario, 'Faltante de inventario',      AnomalyThresholdType::Cantidad, AnomalySeverity::Advertencia, null],
            [AnomalyRuleCode::Discrepancia3Way,   'Discrepancia 3-way',          AnomalyThresholdType::Monto,    AnomalySeverity::Critica,     null],
            [AnomalyRuleCode::CuentaVencida,      'Cuenta por cobrar vencida',   AnomalyThresholdType::Tiempo,   AnomalySeverity::Advertencia, null],
            [AnomalyRuleCode::OmisionRegistro,    'Omisión de registro',         AnomalyThresholdType::Tiempo,   AnomalySeverity::Informativa, null],
            [AnomalyRuleCode::VentaSinSesion,     'Venta sin sesión de caja',    AnomalyThresholdType::Cantidad, AnomalySeverity::Critica,     null],
        ];

        foreach ($rules as [$code, $name, $type, $severity, $threshold]) {
            // Race-safe, respaldado por uniq_anomaly_rule_code. bypass acotado.
            AnomalyRule::unguarded(fn () => AnomalyRule::query()->createOrFirst(
                ['business_id' => $business->id, 'code' => $code->value],
                [
                    'name'             => $name,
                    'threshold_type'   => $type->value,
                    'default_severity' => $severity->value,
                    'threshold_value'  => $threshold,
                    'is_active'        => true,
                ]
            ));
        }
    }
}
