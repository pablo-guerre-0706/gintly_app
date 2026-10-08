<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Alta pública canónica (POST /api/v1/auth/register). NO extiende BaseTenantRequest: es un endpoint
 * público, sin sesión ni business_id. Un visitante con sesión HUMANA autenticada (guard web) recibe 403
 * aquí mismo (authorize), antes de validar o crear nada. La Idempotency-Key se valida DESDE EL HEADER y no
 * puede sustituirse por un campo del body (que se rechaza como clave desconocida).
 */
final class RegisterRequest extends FormRequest
{
    /** Allowlist estricta del body (todo lo demás → 422 sobre su ruta). */
    private const ALLOWED_TOP = ['business', 'owner'];

    private const ALLOWED_BUSINESS = ['name', 'timezone'];

    private const ALLOWED_OWNER = ['first_name', 'last_name', 'email', 'password', 'password_confirmation'];

    /** Instantánea del body ORIGINAL (antes de fusionar el header), para la allowlist de claves. */
    private array $rawBody = [];

    public function authorize(): bool
    {
        // Un visitante YA autenticado como humano no puede registrar negocios. Se comprueba el guard web y la
        // sesión real (no el guard por defecto). Devuelve 403 sin validar ni crear nada.
        return ! $this->hasAuthenticatedHumanSession();
    }

    protected function failedAuthorization(): void
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Una sesión autenticada no puede registrar un nuevo negocio. Cierre sesión para continuar.'
        );
    }

    private function hasAuthenticatedHumanSession(): bool
    {
        // auth('web') refleja la sesión stateful del SPA; se comprueba el guard web explícito y la sesión
        // real, no solo el guard por defecto.
        return (bool) auth('web')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business'                    => ['required', 'array'],
            'business.name'               => ['required', 'string', 'max:150'],
            'business.timezone'           => ['required', 'string', 'max:64', 'timezone'],

            'owner'                       => ['required', 'array'],
            'owner.first_name'            => ['required', 'string', 'max:150'],
            'owner.last_name'             => ['required', 'string', 'max:150'],
            'owner.email'                 => ['required', 'string', 'email:rfc', 'max:180'],
            'owner.password'              => ['required', 'string', 'confirmed', Password::defaults()],
            'owner.password_confirmation' => ['required', 'string'],

            // Validada DESDE EL HEADER (fusionada en prepareForValidation). El body no puede aportarla.
            'idempotency_key'             => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Instantánea del body tal como llegó (sin el header), para la allowlist estricta de claves.
        $this->rawBody = $this->all();

        // Normalización SOLO sobre valores del tipo esperado (arrays/objetos o tipos incorrectos → validación, no 500).
        $business = $this->input('business');
        if (is_array($business)) {
            if (isset($business['name']) && is_string($business['name'])) {
                $business['name'] = trim($business['name']);
            }
            // timezone: sin normalizar (la valida la regla 'timezone').
            $this->merge(['business' => $business]);
        }

        $owner = $this->input('owner');
        if (is_array($owner)) {
            if (isset($owner['first_name']) && is_string($owner['first_name'])) {
                $owner['first_name'] = $this->squish($owner['first_name']);
            }
            if (isset($owner['last_name']) && is_string($owner['last_name'])) {
                $owner['last_name'] = $this->squish($owner['last_name']);
            }
            if (isset($owner['email']) && is_string($owner['email'])) {
                $owner['email'] = mb_strtolower(trim($owner['email']));
            }
            // password / password_confirmation: NUNCA se tocan (espacios preservados; ver TrimStrings::skipWhen).
            $this->merge(['owner' => $owner]);
        }

        // Idempotency-Key SOLO desde el header. Si el body trajo una clave homónima, la allowlist la rechaza.
        $header = $this->header('Idempotency-Key');
        if (is_string($header)) {
            // Representación consistente del UUID (minúsculas); su validez la comprueba la regla 'uuid'.
            $this->merge(['idempotency_key' => mb_strtolower(trim($header))]);
        }
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnknownKeys($validator);
                $this->assertCombinedNameFits($validator);
            },
        ];
    }

    /**
     * Allowlist EXPLÍCITA: cualquier clave fuera de contrato (incluidas privilegiadas como business_id, slug,
     * role, roles, permissions, status, plan, payment, card, etc., aun con null/false/''), produce 422 en su ruta.
     */
    private function rejectUnknownKeys(Validator $validator): void
    {
        foreach (array_keys($this->rawBody) as $key) {
            if (! in_array($key, self::ALLOWED_TOP, true)) {
                $validator->errors()->add((string) $key, "El campo {$key} no está permitido en el registro.");
            }
        }

        if (is_array($this->rawBody['business'] ?? null)) {
            foreach (array_keys($this->rawBody['business']) as $key) {
                if (! in_array($key, self::ALLOWED_BUSINESS, true)) {
                    $validator->errors()->add("business.{$key}", "El campo business.{$key} no está permitido.");
                }
            }
        }

        if (is_array($this->rawBody['owner'] ?? null)) {
            foreach (array_keys($this->rawBody['owner']) as $key) {
                if (! in_array($key, self::ALLOWED_OWNER, true)) {
                    $validator->errors()->add("owner.{$key}", "El campo owner.{$key} no está permitido.");
                }
            }
        }
    }

    /** `users.name` = nombre + apellido normalizados; no puede exceder 150 caracteres físicos. */
    private function assertCombinedNameFits(Validator $validator): void
    {
        $first = $this->input('owner.first_name');
        $last  = $this->input('owner.last_name');

        if (! is_string($first) || ! is_string($last)) {
            return; // tipos inválidos ya los marca la validación base.
        }

        $combined = trim($first.' '.$last);

        if (mb_strlen($combined) > 150) {
            $validator->errors()->add(
                'owner.first_name',
                'El nombre y apellido combinados no pueden exceder los 150 caracteres.'
            );
        }
    }

    /** Datos estructurados y normalizados para el servicio (sin la clave de idempotencia). */
    public function registrationData(): array
    {
        return [
            'business' => [
                'name'     => (string) $this->validated('business.name'),
                'timezone' => (string) $this->validated('business.timezone'),
            ],
            'owner' => [
                'first_name' => (string) $this->validated('owner.first_name'),
                'last_name'  => (string) $this->validated('owner.last_name'),
                'email'      => (string) $this->validated('owner.email'),
                'password'   => (string) $this->validated('owner.password'), // exacta (espacios preservados)
            ],
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }

    /** Colapsa espacios internos repetidos y recorta extremos (nombres personales). */
    private function squish(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business.required'            => 'Los datos del negocio son obligatorios.',
            'business.array'               => 'Los datos del negocio no tienen un formato válido.',
            'business.name.required'       => 'El nombre del negocio es obligatorio.',
            'business.name.max'            => 'El nombre del negocio no puede exceder los 150 caracteres.',
            'business.timezone.required'   => 'La zona horaria es obligatoria.',
            'business.timezone.timezone'   => 'La zona horaria no es válida.',
            'business.timezone.max'        => 'La zona horaria no puede exceder los 64 caracteres.',
            'owner.required'               => 'Los datos del propietario son obligatorios.',
            'owner.array'                  => 'Los datos del propietario no tienen un formato válido.',
            'owner.first_name.required'    => 'El nombre del propietario es obligatorio.',
            'owner.last_name.required'     => 'El apellido del propietario es obligatorio.',
            'owner.email.required'         => 'El correo electrónico es obligatorio.',
            'owner.email.email'            => 'El correo electrónico no tiene un formato válido.',
            'owner.email.max'              => 'El correo electrónico no puede exceder los 180 caracteres.',
            'owner.password.required'      => 'La contraseña es obligatoria.',
            'owner.password.confirmed'     => 'La confirmación de la contraseña no coincide.',
            'owner.password_confirmation.required' => 'Debe confirmar la contraseña.',
            'idempotency_key.required'     => 'El encabezado Idempotency-Key es obligatorio.',
            'idempotency_key.uuid'         => 'El encabezado Idempotency-Key debe ser un UUID válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business.name'      => 'nombre del negocio',
            'business.timezone'  => 'zona horaria',
            'owner.first_name'   => 'nombre',
            'owner.last_name'    => 'apellido',
            'owner.email'        => 'correo electrónico',
            'owner.password'     => 'contraseña',
            'idempotency_key'    => 'clave de idempotencia',
        ];
    }
}
