<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = (int) $this->route('step');

        return match ($step) {
            // Paso 1: Configuración inicial del perfil
            1 => [
                'nombres'   => 'required|string|max:100',
                'apellidos' => 'required|string|max:100',
                'email'     => 'required|email|max:150',
            ],

            // Paso 2: Información de la tienda
            2 => [
                'nombre_tienda'      => 'required|string|max:150',
                'pais'               => 'required|string|max:100',
                'ciudad'             => 'required|string|max:100',
                'codigo_postal'      => 'required|string|max:15',
                'direccion'          => 'required|string|max:255',
                'email_tienda'       => 'nullable|email|max:150',
                'telefono_tienda'    => 'nullable|string|max:20',
                'numero_sucursales' => 'required|integer|min:1|max:1000',
                'ruc_identificacion' => 'required|string|max:50',
            ],

            // Paso 3: Tipo de negocio
            3 => [
                'tipo_negocio' => 'required|string|max:50',
            ],

            // Paso 5: Creación de empleado
            5 => [
                'empleado_nombres'   => 'required|string|max:100',
                'empleado_apellidos' => 'required|string|max:100',
                'empleado_email'     => 'required|email|max:150',
                'empleado_telefono'  => 'nullable|string|max:20',
                'empleado_rol'       => 'required|string|max:50',
                'password'           => 'required|string|min:12|max:64',
            ],

            // Paso 7: Plan seleccionado
            7 => [
                'plan_seleccionado' => 'required|string|max:50',
                'frecuencia_pago'   => 'required|in:mensual,anual',
            ],

            // Paso 8: Pago y facturación
            8 => [
                'titular_razon_social' => 'required|string|max:150',
                'email_facturacion'    => 'required|email|max:150',
                'metodo_pago'          => 'required|in:tarjeta,transferencia',
                'numero_tarjeta'       => 'required_if:metodo_pago,tarjeta|nullable|string|min:13|max:19',
                'nombre_tarjeta'       => 'required_if:metodo_pago,tarjeta|nullable|string|max:150',
                'vencimiento'          => 'required_if:metodo_pago,tarjeta|nullable|string|size:5',
                'cvc'                  => 'required_if:metodo_pago,tarjeta|nullable|string|min:3|max:4',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'required'    => 'Este campo es de carácter obligatorio.',
            'email'       => 'Debes ingresar un correo electrónico válido.',
            'min'         => 'El campo debe tener al menos :min caracteres.',
            'max'         => 'El campo no puede exceder los :max caracteres.',
            'size'        => 'El campo debe tener exactamente :size caracteres.',
            'required_if' => 'Este campo es obligatorio al seleccionar pago con tarjeta.',
        ];
    }
}