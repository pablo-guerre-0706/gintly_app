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
    //public function created(Business $business): void
    //{
        // En este punto $business->id YA existe.
        // Creamos su "Consumidor Final" atado a este negocio.
        //Customer::create([
            //'business_id'     => $business->id,
            //'name'            => 'Consumidor Final',
            //'document_type'   => 'generico',
            //'document_number' => null,
            //'is_generic'      => true,
            //'is_active'       => true,
        //]);
    //}

}
