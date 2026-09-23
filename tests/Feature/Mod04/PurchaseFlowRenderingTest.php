<?php

declare(strict_types=1);

namespace Tests\Feature\Mod04;

use App\Exceptions\InvalidPurchaseStateException;
use App\Models\GoodsReceiptItem;
use App\Models\Supplier;
use App\Enums\SupplierStatus;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

/**
 * MOD-04 sin base de datos.
 *
 *  - Sobrepago de CxP → 422 (validación de monto); CxP congelada → 409 (estado).
 *    Antes ambos degradaban a 409.
 *  - La aprobación de proveedor comparaba enum contra string (siempre distinto):
 *    rechazaba toda orden. Se documenta la semántica correcta del cast.
 *  - goods_receipt_items.matched es columna real y la evidencia la persiste.
 *
 * El comportamiento con datos (crear/emitir orden, recibir con match) depende de
 * MySQL y queda pendiente.
 */
final class PurchaseFlowRenderingTest extends TestCase
{
    public function test_sobrepago_de_cxp_es_422(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            InvalidPurchaseStateException::paymentExceedsBalance(9)
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_cxp_congelada_es_409(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            InvalidPurchaseStateException::payableBlocked(9)
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_estado_de_orden_invalido_es_409(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            InvalidPurchaseStateException::orderNotReceivable(3)
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_status_de_proveedor_es_enum_no_string(): void
    {
        // Réplica exacta de lo que hace value('status'): hidratar y acceder al atributo.
        $supplier = (new Supplier())->newFromBuilder(['status' => 'aprobado']);

        $this->assertInstanceOf(SupplierStatus::class, $supplier->status);
        // Comparación corregida (enum vs enum) reconoce al aprobado…
        $this->assertTrue($supplier->status === SupplierStatus::Aprobado);
        // …mientras que la comparación anterior (enum vs string) lo rechazaba siempre.
        $this->assertFalse($supplier->status === SupplierStatus::Aprobado->value);
    }

    public function test_goods_receipt_item_persiste_matched(): void
    {
        $this->assertContains('matched', (new GoodsReceiptItem())->getFillable());
    }
}
