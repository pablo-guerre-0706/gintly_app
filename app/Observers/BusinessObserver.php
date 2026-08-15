<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DocumentType;
use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalySeverity;
use App\Enums\AnomalyThresholdType;
use App\Models\AnomalyRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;



final class BusinessObserver
{
    // Se dispara automaticamente despues que un negocio se guarda en la BD.
    public function created(Business $business): void
    {
        DB::transaction(function () use ($business): void {
            $this->seedGenericCustomer($business);
            $this->seedDocumentSequences($business);
            $this->seedAnomalyRules($business);

            app(\Database\Seeders\RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);
        });
    }

    // Cliente genérico "Consumidor Final": uno por negocio, protegido.
    private function seedGenericCustomer(Business $business): void
    {
        Customer::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'is_generic'  => true,
            ],
            [
                'name'          => 'Consumidor Final',
                'document_type' => DocumentType::Generico,
                'document_number' => null,
                'is_active'     => true,
            ]
        );
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
            'sales_return' => 'DV-',
        ];

        foreach ($sequences as $documentType => $prefix) {
            DocumentSequence::query()->firstOrCreate(
                [
                    'business_id'   => $business->id,
                    'document_type' => $documentType,
                ],
                [
                    'prefix'      => $prefix,
                    'next_number' => 1,
                ]
            );
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
            AnomalyRule::query()->firstOrCreate(
                [
                    'business_id' => $business->id,
                    'code' => $code->value],
                [
                    'name'             => $name,
                    'threshold_type'   => $type->value,
                    'default_severity' => $severity->value,
                    'threshold_value'  => $threshold,
                    'is_active'        => true,
                ]
            );
        }
    }
}
