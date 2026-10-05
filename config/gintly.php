<?php

declare(strict_types=1);
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Anomaly;
use App\Models\AnomalyEvent;
use App\Models\AnomalyRule;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Business;
use App\Models\BusinessGoal;
use App\Models\CashCount;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterAssignment;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\CreditNoteResolution;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\DocumentSequence;
use App\Models\ExchangeRate;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\KpiSnapshot;
use App\Models\PhysicalCount;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivablePayment;
use App\Models\ReconciliationRun;
use App\Models\RegisterWizard;
use App\Models\ReportDefinition;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Models\TaxRule;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;

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
    'business' => Business::class,
    'user' => User::class,
    'branch' => Branch::class,
    'audit_log' => AuditLog::class,

    // MOD-02 — Catálogo y Datos Maestros
    'category' => Category::class,
    'brand' => Brand::class,
    'unit_of_measure' => UnitOfMeasure::class,
    'product' => Product::class,
    'product_recipe' => ProductRecipe::class,

    // MOD-03 — Inventario Lógico y Bodega Física
    'warehouse' => Warehouse::class,
    'warehouse_assignment' => WarehouseAssignment::class,
    'stock_level' => StockLevel::class,
    'physical_count' => PhysicalCount::class,
    'stock_transfer' => StockTransfer::class,
    'stock_transfer_item' => StockTransferItem::class,
    'inventory_adjustment' => InventoryAdjustment::class,
    'inventory_movement' => InventoryMovement::class,

    // MOD-04 — Compras, Proveedores y Recepción
    'supplier' => Supplier::class,
    'supplier_location' => SupplierLocation::class,
    'purchase_order' => PurchaseOrder::class,
    'purchase_order_item' => PurchaseOrderItem::class,
    'goods_receipt' => GoodsReceipt::class,
    'goods_receipt_item' => GoodsReceiptItem::class,
    'account_payable' => AccountPayable::class,

    // MOD-05 — Clientes, Perfilamiento y Fidelidad
    'customer' => Customer::class,
    'customer_address' => CustomerAddress::class,

    // MOD-06 — Gestión de Caja
    'cash_register' => CashRegister::class,
    'cash_register_assignment' => CashRegisterAssignment::class,
    'cash_session' => CashSession::class,
    'cash_movement' => CashMovement::class,
    'cash_count' => CashCount::class,
    'exchange_rate' => ExchangeRate::class,

    // MOD-07 — Ventas, Facturación e Inmutabilidad
    'sale' => Sale::class,
    'sale_item' => SaleItem::class,
    'invoice' => Invoice::class,
    'invoice_payment' => InvoicePayment::class,
    'document_sequence' => DocumentSequence::class,
    'tax_rule' => TaxRule::class,

    // MOD-08 — Ventas al Crédito y CxC
    'account_receivable' => AccountReceivable::class,
    'receivable_payment' => ReceivablePayment::class,

    // MOD-09 — Entregas y Retiros
    'dispatch' => Dispatch::class,
    'dispatch_item' => DispatchItem::class,

    // MOD-10 — Devoluciones, Reingreso y Mermas
    'sales_return' => SalesReturn::class,
    'sales_return_item' => SalesReturnItem::class,
    'credit_note' => CreditNote::class,
    'credit_note_resolution' => CreditNoteResolution::class,

    // MOD-11 — Conciliación, Alertas y Anomalías
    'anomaly_rule' => AnomalyRule::class,
    'reconciliation_run' => ReconciliationRun::class,
    'anomaly' => Anomaly::class,
    'anomaly_event' => AnomalyEvent::class,

    // MOD-12 — Reportería, KPIs e Inteligencia de Negocios
    'business_goal' => BusinessGoal::class,
    'kpi_snapshot' => KpiSnapshot::class,
    'report_definition' => ReportDefinition::class,

    // MOD-01 (Fase 3) — Perfiles operativos de ROL-03
    'user_operative_profile' => UserOperativeProfile::class,

    // Onboarding / Registro (pre-tenant)
    // RegisterWizard es un modelo VIVO (RegisterWizardController + RegisterWizardRequest
    // + ruta web + migración create_register_wizards): captura el formulario de alta
    // multi-paso ANTES de que exista el negocio. La invariante del proyecto exige que
    // TODO modelo de app/Models tenga un alias estable de morphMap (MorphMapIntegrityTest),
    // para que ninguna columna polimórfica persista el FQCN. Alias snake_case singular de
    // la tabla `register_wizards`. Al derivarse auditable_types de array_keys(morph_map),
    // queda disponible en la lista blanca de auditoría (sin forzar auditoría automática).
    'register_wizard' => RegisterWizard::class,
];

return [

    // Aislamiento multi-negocio
    'tenant' => [
        'guards' => ['web'],   // El usuario vive en el guard web (Sanctum es solo transporte)
        'api_guard' => 'web',     // lo leen VerifiesCurrentPassword (current_password:web) y SetPermissionsTeamId
    ],

    // Seguridad y Autenticacion
    'auth' => [
        'max_attempts' => 5,     // Umbral parametrizable del rate-limit
        'decay_seconds' => 60,
    ],

    // Bitácora de auditoría
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
