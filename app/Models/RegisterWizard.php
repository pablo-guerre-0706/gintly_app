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
        'nombre',
        'apellido',
        'correo',
        'telefono',
        'codigo_pais',
        'password',
        'nombre_tienda',
        'pais_region',
        'ciudad',
        'codigo_postal',
        'direccion',
        'correo_tienda',
        'numero_convencional',
        'numero_sucursales',
        'ruc',
        'tipo_negocio',
        'zona_horaria',
        'moneda',
        'fecha_creacion',
        'empleado_nombre',
        'empleado_apellido',
        'empleado_correo',
        'empleado_telefono',
        'empleado_rol',
        'plan',
        'ciclo',
        'billing_nombre',
        'billing_razon',
        'billing_correo',
        'metodo_pago',
        'numero_tarjeta',
        'nombre_tarjeta',
        'vencimiento',
        'cvc',
        'referencia_transferencia',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}