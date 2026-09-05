<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RegisterWizard;
use Illuminate\Support\Facades\Validator;

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

        if ($step === 9) {
            return view('auth.register-step9', compact('formData', 'totalSteps'));
        }

        return view("auth.register-step{$step}", [
            'currentStep' => $step,
            'totalSteps'  => $totalSteps,
            'formData'    => $formData,
        ]);
    }

    /**
     * Procesa, valida y guarda la información enviada por GET, avanzando al siguiente paso.
     */
    public function storeStep(Request $request, $step)
    {
        $step = (int) $step;

        // Reglas de validación dinámicas para cada uno de los 9 pasos
        $rules = [];

        switch ($step) {
            case 1:
                $rules = [
                    'nombre'      => 'nullable|string|min:2',
                    'apellido'    => 'nullable|string|min:2',
                    'correo'      => 'nullable|email',
                    'telefono'    => 'nullable|string',
                    'codigo_pais' => 'nullable|string',
                    'password'    => 'nullable|string|min:12',
                ];
                break;
            case 2:
                // Agrega aquí las reglas del paso 2 si las tienes
                break;
            case 3:
                $rules = [
                    'tipo_negocio' => 'required|string',
                ];
                break;
            case 4:
                $rules = [
                    'moneda'         => 'required|string|in:USD,NIO,EUR',
                    'fecha_creacion' => 'required|date|before_or_equal:today',
                ];
                break;
            case 5:
            case 6:
            case 7:
                // Agrega las reglas correspondientes para los pasos intermedios restantes
                break;
        }

        // Usamos Validator explícito para manejar mejor peticiones GET en caso de error
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('register.step', ['step' => $step])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Filtrar valores nulos para no sobrescribir datos previos en la sesión
        $filteredData = array_filter($validated, function ($value) {
            return !is_null($value) && $value !== '';
        });

        // Guardar y fusionar en la sesión
        $currentData = session('registration_wizard', []);
        session(['registration_wizard' => array_merge($currentData, $filteredData)]);

        // Si es el paso 6 (Confirma plan y avanza ala vista de exito, antes de guardar en la BD)
        if ($step === 6) {
            $allData = session('registration_wizard', []);
            RegisterWizard::create($allData);
            session()->forget('registration_wizard');

            return redirect()->route('register.step', ['step' => 7]);
        }

        // Si es el paso 7 (El paso final de felicitaciones y lo mandamos al login)
        if ($step === 7) {
            $allData = session('registration_wizard', []);
            RegisterWizard::create($allData);
            session()->forget('registration_wizard');

            return redirect()->route('login')->with('success', '¡Registro completado! Ingresa con tus credenciales de prueba.');
        }

        // ESTE UNICO RETURN sirve de puente para los pasos 1, 2, 3, 4 y 5
        return redirect()->route('register.step', ['step' => $step + 1]);
    }
}
