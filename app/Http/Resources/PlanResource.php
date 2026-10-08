<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan del catálogo para contratar. Precios ANUNCIADOS en NIO (minor = centavos y decimal exacto en string;
 * nunca float). Expone límites y capacidades acumulativas. No expone variantes ni credenciales del proveedor.
 *
 * @property array{key:string,name:string,prices:array,limits:array,features:array} $resource
 */
final class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key'      => $this->resource['key'],
            'name'     => $this->resource['name'],
            'currency' => (string) config('billing.catalog_currency', 'NIO'),
            'prices'   => $this->resource['prices'], // { monthly:{nio_minor,nio}, annual:{nio_minor,nio} }
            'limits'   => $this->resource['limits'],  // { branches:int, cash_sessions:int|null }
            'features' => $this->resource['features'],
        ];
    }
}
