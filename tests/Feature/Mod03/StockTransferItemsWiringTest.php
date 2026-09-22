<?php

declare(strict_types=1);

namespace Tests\Feature\Mod03;

use App\Exceptions\InvalidCountStateException;
use App\Http\Requests\Api\V1\StockTransfer\CompleteStockTransferRequest;
use App\Models\Scopes\BusinessScope;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

/**
 * Cierre MOD-03 (opción A): las líneas del traspaso se persisten y la confirmación
 * las consume; el endpoint /complete ya no acepta ítems; un pendiente sin líneas se
 * rechaza con 409 controlado; la baja de bodega bloquea por dependencias.
 *
 * Cobertura sin base de datos (forma/cableado). El comportamiento con datos reales
 * queda cubierto por MySQL (ver informe).
 */
final class StockTransferItemsWiringTest extends TestCase
{
    public function test_traspaso_sin_lineas_se_rechaza_con_409(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            InvalidCountStateException::transferHasNoLines(42)
        );

        $this->assertSame(409, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('INVALID_STATE', $payload['error'] ?? null);
    }

    public function test_stock_transfer_tiene_relacion_items(): void
    {
        $relation = (new StockTransfer())->items();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(StockTransferItem::class, $relation->getRelated());
        $this->assertSame('stock_transfer_id', $relation->getForeignKeyName());
    }

    public function test_stock_transfer_item_aisla_por_negocio_y_esta_en_el_morphmap(): void
    {
        $this->assertArrayHasKey(
            BusinessScope::class,
            (new StockTransferItem())->getGlobalScopes()
        );

        $map = (array) config('gintly.audit.morph_map');
        $this->assertArrayHasKey('stock_transfer_item', $map);
        $this->assertSame(StockTransferItem::class, $map['stock_transfer_item']);
    }

    public function test_complete_request_ya_no_exige_items(): void
    {
        $this->assertSame([], (new CompleteStockTransferRequest())->rules());
    }

    public function test_warehouse_expone_las_guardas_de_baja(): void
    {
        $this->assertTrue(method_exists(Warehouse::class, 'deletionBlocker'));
        $this->assertTrue(method_exists(Warehouse::class, 'hasPendingTransfers'));
        $this->assertTrue(method_exists(Warehouse::class, 'isUndesignatedDefault'));
    }
}
