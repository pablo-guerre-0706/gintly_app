<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DocumentType;
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

    /**
     * Catálogo base de 6 reglas de anomalía.
     * @return void
     */
    private function seedAnomalyRules(Business $business): void
    {
        if (! class_exists(AnomalyRule::class)) {
            return;
        }

        foreach ($this->anomalyRuleCatalog() as $rule) {
            AnomalyRule::query()->firstOrCreate(
                [
                    'business_id' => $business->id,
                    'code'        => $rule['code'],
                ],
                [
                    'name'             => $rule['name'],
                    'threshold_type'   => $rule['threshold_type'],
                    'default_severity' => $rule['default_severity'],
                    'threshold_value'  => $rule['threshold_value'],
                ]
            );
        }
    }

    /**
     * Catálogo canónico de reglas de anomalía.
     * @return array<int, array{code: string, name: string, threshold_type: string, default_severity: string, threshold_value: float|null}>
     */
    private function anomalyRuleCatalog(): array
    {
        return [
            [
                'code'             => 'descuadre_caja',
                'name'             => 'Descuadre de caja',
                'threshold_type'   => 'monto',
                'default_severity' => 'advertencia',
                'threshold_value'  => 50.00,
            ],
            [
                'code'             => 'faltante_inventario',
                'name'             => 'Faltante de inventario',
                'threshold_type'   => 'porcentaje',
                'default_severity' => 'advertencia',
                'threshold_value'  => 2.00,
            ],
            [
                'code'             => 'discrepancia_3way',
                'name'             => 'Discrepancia 3-way match',
                'threshold_type'   => 'monto',
                'default_severity' => 'critica',
                'threshold_value'  => 0.00,
            ],
            [
                'code'             => 'cuenta_vencida',
                'name'             => 'Cuenta por cobrar vencida',
                'threshold_type'   => 'tiempo',
                'default_severity' => 'advertencia',
                'threshold_value'  => null,
            ],
            [
                'code'             => 'omision_registro',
                'name'             => 'Omisión de registro',
                'threshold_type'   => 'cantidad',
                'default_severity' => 'advertencia',
                'threshold_value'  => null,
            ],
            [
                'code'             => 'venta_sin_sesion',
                'name'             => 'Venta sin sesión de caja',
                'threshold_type'   => 'cantidad',
                'default_severity' => 'critica',
                'threshold_value'  => 0.00,
            ],
        ];
    }
}
