<?php

namespace App\Observers;

use App\Enums\DocumentType;
use App\Models\Business;
use App\Models\Customer;
use App\Models\AnomalyRule;

class BusinessObserver
{
    /**
     * Se dispara automáticamente DESPUÉS de que un negocio
     * se guarda en la base de datos.
     */
    public function created(Business $business): void
    {
        // MOD-05: cliente genérico "Consumidor Final" (uno por negocio, protegido).
        $exists = Customer::query()
            ->where('business_id', $business->id)
            ->where('is_generic', true)
            ->exists();

        if (! $exists) {
            $generic = new Customer([
                'name'          => 'Consumidor Final',
                'document_type' => DocumentType::Generico,
                'is_active'     => true,
            ]);
            $generic->business_id = $business->id;  // atributos protegidos: asignación directa
            $generic->is_generic  = true;
            $generic->save();
        }

        foreach (['invoice' => 'F-', 'credit_note' => 'NC-'] as $type => $prefix) {
            $exists = \App\Models\DocumentSequence::query()
                ->where('business_id', $business->id)->where('document_type', $type)->exists();
            if (! $exists) {
                $seq = new \App\Models\DocumentSequence(['document_type' => $type, 'prefix' => $prefix, 'next_number' => 1]);
                $seq->business_id = $business->id;
                $seq->save();
            }
        }


        // TODO (MOD-11): sembrar el catálogo base de anomaly_rules (6 reglas del BRD).





        // 2- Catalogo base de reglas de anomalias (MOD-11)
        $reglas = [
            ['code' => 'descuadre_caja',      'name' => 'Descuadre de caja',         'threshold_type' => 'monto',      'default_severity' => 'advertencia', 'threshold_value' => 50.00],
            ['code' => 'faltante_inventario', 'name' => 'Faltante de inventario',    'threshold_type' => 'porcentaje', 'default_severity' => 'advertencia', 'threshold_value' => 2.00],
            ['code' => 'discrepancia_3way',   'name' => 'Discrepancia 3-way match',  'threshold_type' => 'monto',      'default_severity' => 'critica',     'threshold_value' => 0.00],
            ['code' => 'cuenta_vencida',      'name' => 'Cuenta por cobrar vencida', 'threshold_type' => 'tiempo',     'default_severity' => 'advertencia', 'threshold_value' => null],
            ['code' => 'omision_registro',    'name' => 'Omisión de registro',       'threshold_type' => 'cantidad',   'default_severity' => 'advertencia', 'threshold_value' => null],
            ['code' => 'venta_sin_sesion',    'name' => 'Venta sin sesión de caja',  'threshold_type' => 'cantidad',   'default_severity' => 'critica',     'threshold_value' => 0.00],
        ];

        /* foreach ($reglas as $r) {
            AnomalyRule::firstOrCreate(
                ['business_id' => $business->id, 'code' => $r['code']],
                $r + ['is_active' => true]
            );
        } */
    }
}
