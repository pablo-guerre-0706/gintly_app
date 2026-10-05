<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SupplierLocation;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use Illuminate\Validation\Validator;

/**
 * MOD-04 · Confirmación/corrección manual del marcador (ROL-01/ROL-02). Las coordenadas son opcionales:
 * si se envían, corrigen y marcan procedencia 'manual'; si no, confirman las ya geocodificadas. El Service
 * rechaza confirmar sin coordenadas. Si se envía una coordenada, se exige la otra y en rango.
 */
final class ConfirmSupplierLocationRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            && ($this->user()?->can('manage', [SupplierLocation::class, $supplier]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $lat = $this->input('latitude');
            $lng = $this->input('longitude');
            if (($lat === null) !== ($lng === null)) {
                $validator->errors()->add('latitude', 'Debe indicar latitud y longitud juntas, o ninguna.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between'  => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
        ];
    }
}
