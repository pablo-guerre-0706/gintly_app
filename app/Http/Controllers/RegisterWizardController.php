<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RegisterWizard;
use App\Models\User;
use App\Models\Business;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterWizardController extends Controller
{
    /**
     * Muestra la vista del paso correspondiente del asistente (1 al 7).
     */
    public function showStep($step)
    {
        $step = (int) $step;
        $totalSteps = 7;

        if ($step < 1 || $step > $totalSteps) {
            return redirect()->route('register.step', ['step' => 1]);
        }

        $formData = session('registration_wizard', []);

        return view("auth.register-step{$step}", [
            'currentStep' => $step,
            'totalSteps'  => $totalSteps,
            'formData'    => $formData,
        ]);
    }

    /**
     * Procesa, valida y guarda la información enviada, avanzando al siguiente paso.
     */
    public function storeStep(Request $request, $step)
    {
        $step = (int) $step;
        $rules = [];

        switch ($step) {
            case 1:
                $rules = [
                    'nombre'      => 'required|string|min:2',
                    'apellido'    => 'required|string|min:2',
                    'correo'      => 'required|email|unique:users,email',
                    'telefono'    => 'required|string',
                    'codigo_pais' => 'required|string',
                    'password'    => 'required|string|min:12',
                ];
                break;

            case 2:
                $rules = [
                    'nombre_tienda'       => 'required|string|min:2',
                    'pais_region'         => 'required|string|min:2',
                    'ciudad'              => 'required|string|min:2',
                    'codigo_postal'       => 'required|string|min:3',
                    'direccion'           => 'required|string|min:5',
                    'correo_tienda'       => 'required|email',
                    'numero_convencional' => 'required|string|min:7|max:15',
                    'numero_sucursales'   => 'required|integer|min:1',
                    'ruc'                 => 'required|string|min:5|unique:businesses,ruc',
                ];
                break;

            case 3:
                $rules = [
                    'tipo_negocio' => 'required|string',
                ];
                break;

            case 4:
                $rules = [
                    'zona_horaria'   => 'required|string',
                    'moneda'         => 'required|string|in:USD,NIO,EUR',
                    'fecha_creacion' => 'required|date|before_or_equal:today',
                ];
                break;

            case 5:
                $rules = [
                    'empleado_nombre'   => 'required|string|min:2',
                    'empleado_apellido' => 'required|string|min:2',
                    'empleado_correo'   => 'required|email',
                    'empleado_telefono' => 'required|string',
                    'empleado_rol'      => 'required|string',
                ];
                break;

            case 6:
                $rules = [
                    'plan'                     => 'required|string|in:inicial,comercio,cadena',
                    'ciclo'                    => 'required|string|in:monthly,annual',
                    'billing_nombre'           => 'required|string|min:2',
                    'billing_razon'            => 'required|string',
                    'billing_correo'           => 'required|email',
                    'metodo_pago'              => 'required|string|in:tarjeta,transferencia',
                    'numero_tarjeta'           => 'required_if:metodo_pago,tarjeta|nullable|string',
                    'nombre_tarjeta'           => 'required_if:metodo_pago,tarjeta|nullable|string',
                    'vencimiento'              => 'required_if:metodo_pago,tarjeta|nullable|string',
                    'cvc'                      => 'required_if:metodo_pago,tarjeta|nullable|string',
                    'referencia_transferencia' => 'required_if:metodo_pago,transferencia|nullable|string',
                ];
                break;

            case 7:
                break;
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('register.step', ['step' => $step])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $currentData = session('registration_wizard', []);
        $updatedData = array_merge($currentData, $validated);

        session(['registration_wizard' => $updatedData]);

        if ($step === 6) {
            $allData = session('registration_wizard', []);
            $user = null;

            try {
                DB::transaction(function () use ($allData, &$user) {
                    $storeName = $allData['nombre_tienda'] ?? 'Mi Tienda';
                    $email     = $allData['correo'] ?? null;
                    $ruc       = $allData['ruc'] ?? null;

                    // 1. Mapeo seguro para la creación/actualización del Negocio
                    $businessData = [
                        'name'                => $storeName,
                        'slug'                => Str::slug($storeName) . '-' . uniqid(),
                        'country_region'      => $allData['pais_region'] ?? 'Nicaragua',
                        'city'                => $allData['ciudad'] ?? null,
                        'codigo_postal'       => $allData['codigo_postal'] ?? null,
                        'address'             => $allData['direccion'] ?? null,
                        'correo_tienda'       => $allData['correo_tienda'] ?? null,
                        'numero_convencional' => $allData['numero_convencional'] ?? null,
                        'numero_sucursales'   => $allData['numero_sucursales'] ?? 1,
                        'business_type'       => $allData['tipo_negocio'] ?? null,
                        'currency'            => $allData['moneda'] ?? 'NIO',
                        'timezone'            => $allData['zona_horaria'] ?? 'America/Managua',
                        'status'              => 'active',
                    ];

                    // Si hay un RUC válido actualizamos o creamos por RUC; de lo contrario creamos uno nuevo
                    if (!empty($ruc)) {
                        $business = Business::updateOrCreate(['ruc' => $ruc], $businessData);
                    } else {
                        $business = Business::create(array_merge(['ruc' => null], $businessData));
                    }

                    // 2. Crear o actualizar el Usuario
                    $user = User::firstOrNew(['email' => $email]);

                    if (!$user->exists) {
                        $user->fill([
                            'name'      => trim(($allData['nombre'] ?? 'Usuario') . ' ' . ($allData['apellido'] ?? '')),
                            'password'  => Hash::make($allData['password']),
                            'is_active' => true,
                        ]);
                    }

                    $user->business_id = $business->id;
                    $user->branch_id   = null;
                    $user->save();

                    // 3. Vincular el owner del negocio
                    if (!$business->owner_user_id) {
                        $business->update(['owner_user_id' => $user->id]);
                    }

                    // 4. Guardar datos en la tabla RegisterWizard
                    $wizardModel  = new RegisterWizard();
                    $fillableKeys = array_flip($wizardModel->getFillable());

                    $mappedData = array_merge($allData, [
                        'user_id'                  => $user->id,
                        'business_id'              => $business->id,
                        'nombre'                   => $allData['nombre'] ?? null,
                        'apellido'                 => $allData['apellido'] ?? null,
                        'correo'                   => $allData['correo'] ?? null,
                        'telefono'                 => $allData['telefono'] ?? null,
                        'codigo_pais'              => $allData['codigo_pais'] ?? null,
                        'nombre_tienda'            => $allData['nombre_tienda'] ?? null,
                        'pais_region'              => $allData['pais_region'] ?? null,
                        'ciudad'                   => $allData['ciudad'] ?? null,
                        'codigo_postal'            => $allData['codigo_postal'] ?? null,
                        'direccion'                => $allData['direccion'] ?? null,
                        'correo_tienda'            => $allData['correo_tienda'] ?? null,
                        'numero_convencional'      => $allData['numero_convencional'] ?? null,
                        'numero_sucursales'        => $allData['numero_sucursales'] ?? null,
                        'ruc'                      => $allData['ruc'] ?? null,
                        'tipo_negocio'             => $allData['tipo_negocio'] ?? null,
                        'zona_horaria'             => $allData['zona_horaria'] ?? null,
                        'moneda'                   => $allData['moneda'] ?? null,
                        'fecha_creacion'           => $allData['fecha_creacion'] ?? null,
                        'empleado_nombre'          => $allData['empleado_nombre'] ?? null,
                        'empleado_apellido'        => $allData['empleado_apellido'] ?? null,
                        'empleado_correo'          => $allData['empleado_correo'] ?? null,
                        'empleado_telefono'        => $allData['empleado_telefono'] ?? null,
                        'empleado_rol'             => $allData['empleado_rol'] ?? null,
                        'plan'                     => $allData['plan'] ?? null,
                        'ciclo'                    => $allData['ciclo'] ?? null,
                        'billing_nombre'           => $allData['billing_nombre'] ?? null,
                        'billing_razon'            => $allData['billing_razon'] ?? null,
                        'billing_correo'           => $allData['billing_correo'] ?? null,
                        'metodo_pago'              => $allData['metodo_pago'] ?? null,
                        'numero_tarjeta'           => $allData['numero_tarjeta'] ?? null,
                        'nombre_tarjeta'           => $allData['nombre_tarjeta'] ?? null,
                        'vencimiento'              => $allData['vencimiento'] ?? null,
                        'cvc'                      => $allData['cvc'] ?? null,
                        'referencia_transferencia' => $allData['referencia_transferencia'] ?? null,
                    ]);

                    $wizardData = array_intersect_key($mappedData, $fillableKeys);

                    RegisterWizard::updateOrCreate(
                        ['user_id' => $user->id],
                        $wizardData
                    );
                });

                session()->forget('registration_wizard');
                Auth::login($user);

                return redirect()->route('register.step', ['step' => 7]);

            } catch (\Exception $e) {
                return redirect()->route('register.step', ['step' => 6])
                    ->withErrors(['error' => 'Ocurrió un error al registrar tu cuenta: ' . $e->getMessage()])
                    ->withInput();
            }
        }

        return redirect()->route('register.step', ['step' => $step + 1]);
    }
}