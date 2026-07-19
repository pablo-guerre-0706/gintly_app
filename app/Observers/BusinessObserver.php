<?php

namespace App\Observers;

use App\Models\Business;
use App\Models\Customer;

class BusinessObserver
{
    /**
     * Se dispara automáticamente DESPUÉS de que un negocio
     * se guarda en la base de datos.
     */
    // public function //created(Business $business): void
    // {
        // Creamos su "Consumidor Final" atado a este negocio.
        // Customer::firstOrcreate([
            // 'business_id'     => $business->id,
            // 'name'            => 'Consumidor Final',
            // 'document_type'   => 'generico',
            // 'document_number' => null,
            // 'is_generic'      => true,
            //'is_active'       => true,
        // ]);


        // $reglas = [
        //    ['code' => 'descuadre_caja',      'name' => 'Descuadre de caja',        'threshold_type' => 'monto',      'default_severity' => 'advertencia', 'threshold_value' => 50.00],
        //    ['code' => 'faltante_inventario', 'name' => 'Faltante de inventario',   'threshold_type' => 'porcentaje', 'default_severity' => 'advertencia', 'threshold_value' => 2.00],
        //    ['code' => 'discrepancia_3way',   'name' => 'Discrepancia 3-way match', 'threshold_type' => 'monto',      'default_severity' => 'critica',     'threshold_value' => 0.00],
        //    ['code' => 'cuenta_vencida',      'name' => 'Cuenta por cobrar vencida','threshold_type' => 'tiempo',     'default_severity' => 'advertencia', 'threshold_value' => null],
        //    ['code' => 'omision_registro',    'name' => 'Omisión de registro',      'threshold_type' => 'cantidad',   'default_severity' => 'advertencia', 'threshold_value' => null],
        //    ['code' => 'venta_sin_sesion',    'name' => 'Venta sin sesión de caja', 'threshold_type' => 'cantidad',   'default_severity' => 'critica',     'threshold_value' => 0.00],
        // ];

    // foreach ($reglas as $r) {
        // AnomalyRule::firstOrCreate(
        //     ['business_id' => $business->id, 'code' => $r['code']], // criterio único
        //     $r + ['is_active' => true]
        // );
    // }

}
