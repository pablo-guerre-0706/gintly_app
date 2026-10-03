<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterWizard extends Model
{
    use HasFactory;

    protected $table = 'register_wizards';

    protected $fillable = [
        'user_id',
        'nombre_tienda',
        'pais',
        'ciudad',
        'codigo_postal',
        'direccion',
        'email_tienda',
        'telefono_tienda',
        'numero_sucursales',
        'ruc_identificacion',
        'tipo_negocio',
        'empleado_nombres',
        'empleado_apellidos',
        'empleado_email',
        'empleado_telefono',
        'empleado_rol',
        'plan_seleccionado',
        'frecuencia_pago',
        'titular_razon_social',
        'email_facturacion',
        'metodo_pago',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}