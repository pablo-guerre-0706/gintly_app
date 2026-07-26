<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mapa polimórfico canónico
|--------------------------------------------------------------------------
| Fuente ÚNICA de verdad. `auditable_types` se deriva de sus claves mediante
| array_keys(), de modo que ambas listas no pueden divergir: no existe una
| segunda lista que mantener sincronizada.
|
| El alias se PERSISTE en la base de datos (audit_logs.auditable_type,
| model_has_roles.model_type, anomalies.source_type). En consecuencia:
|
|   - Renombrar un alias es una migración de datos, no una edición de config.
|   - Renombrar o mover una clase de modelo NO afecta a los datos existentes:
|     esa es precisamente la razón de ser del morphMap.
|
| Convención: snake_case singular, coincidente con el nombre de la tabla en
| singular. Longitud máxima admitida por el esquema: 120 caracteres
| (audit_logs.auditable_type) y 60 (anomalies.source_type).
*/
$morphMap = [

    // MOD-01 — Seguridad, Identidad y Auditoría
    'business'              => \App\Models\Business::class,
    'user'                  => \App\Models\User::class,
    'branch'                => \App\Models\Branch::class,
    'audit_log'             => \App\Models\AuditLog::class,

    // MOD-02 — Catálogo y Datos Maestros
    'category'              => \App\Models\Category::class,
    'brand'                 => \App\Models\Brand::class,
    'unit_of_measure'       => \App\Models\UnitOfMeasure::class,
    'product'               => \App\Models\Product::class,
    'product_recipe'        => \App\Models\ProductRecipe::class,

    // MOD-03 — Inventario Lógico y Bodega Física
    'warehouse'             => \App\Models\Warehouse::class,
    'stock_level'           => \App\Models\StockLevel::class,
    'physical_count'        => \App\Models\PhysicalCount::class,
    'stock_transfer'        => \App\Models\StockTransfer::class,
    'inventory_adjustment'  => \App\Models\InventoryAdjustment::class,
    'inventory_movement'    => \App\Models\InventoryMovement::class,

    // MOD-04 — Compras, Proveedores y Recepción
    'supplier'              => \App\Models\Supplier::class,
    'purchase_order'        => \App\Models\PurchaseOrder::class,
    'purchase_order_item'   => \App\Models\PurchaseOrderItem::class,
    'goods_receipt'         => \App\Models\GoodsReceipt::class,
    'goods_receipt_item'    => \App\Models\GoodsReceiptItem::class,
    'account_payable'       => \App\Models\AccountPayable::class,

    // MOD-05 — Clientes, Perfilamiento y Fidelidad
    'customer'              => \App\Models\Customer::class,
    'customer_address'      => \App\Models\CustomerAddress::class,

    // MOD-06 — Gestión de Caja
    'cash_register'         => \App\Models\CashRegister::class,
    'cash_session'          => \App\Models\CashSession::class,
    'cash_movement'         => \App\Models\CashMovement::class,

    // MOD-07 — Ventas, Facturación e Inmutabilidad
    'sale'                  => \App\Models\Sale::class,
    'sale_item'             => \App\Models\SaleItem::class,
    'invoice'               => \App\Models\Invoice::class,
    'invoice_sale'          => \App\Models\InvoiceSale::class,
    'invoice_payment'       => \App\Models\InvoicePayment::class,
    'document_sequence'     => \App\Models\DocumentSequence::class,

    // MOD-08 — Ventas al Crédito y CxC
    'account_receivable'    => \App\Models\AccountReceivable::class,
    'receivable_payment'    => \App\Models\ReceivablePayment::class,

    // MOD-09 — Entregas y Retiros
    'dispatch'              => \App\Models\Dispatch::class,
    'dispatch_item'         => \App\Models\DispatchItem::class,

    // MOD-10 — Devoluciones, Reingreso y Mermas
    'sales_return'          => \App\Models\SalesReturn::class,
    'sales_return_item'     => \App\Models\SalesReturnItem::class,
    'credit_note'           => \App\Models\CreditNote::class,

    // MOD-11 — Conciliación, Alertas y Anomalías
    'anomaly_rule'          => \App\Models\AnomalyRule::class,
    'reconciliation_run'    => \App\Models\ReconciliationRun::class,
    'anomaly'               => \App\Models\Anomaly::class,
    'anomaly_event'         => \App\Models\AnomalyEvent::class,

    // MOD-12 — Reportería, KPIs e Inteligencia de Negocios
    'business_goal'         => \App\Models\BusinessGoal::class,
    'kpi_snapshot'          => \App\Models\KpiSnapshot::class,
    'report_definition'     => \App\Models\ReportDefinition::class,
];

return [

    //Aislamiento multi-negocio
    'tenant' => [
        'guards' => ['sanctum'],

        'api_guard' => 'sanctum',
    ],

    //Bitácora de auditoría
    'audit' => [

        // Mapa consumido por Relation::enforceMorphMap() en AppServiceProvider.
        'morph_map' => $morphMap,

        
        // Lista blanca del filtro `auditable_type` de IndexAuditLogRequest.
        'auditable_types' => array_keys($morphMap),

        // Valida que las acciones sean solo las permitidas, evitando hackeos en la base de datos
        'actions' => [
            'create',
            'update',
            'delete',
            'restore',
            'login',
            'logout',
            'role_changed',
            'password_reset',
            'void',
            'approve',
            'suspend',
            'resolve',
            'unlock',
        ],
    ],

];
