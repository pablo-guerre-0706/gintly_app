<?php

declare(strict_types=1);

namespace App\Providers;


use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Anomaly;
use App\Models\AnomalyRule;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Business;
use App\Models\BusinessGoal;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Dispatch;
use App\Models\GoodsReceipt;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\KpiSnapshot;
use App\Models\PhysicalCount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ReconciliationRun;
use App\Models\ReportDefinition;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\BusinessObserver;
use App\Policies\AccountPayablePolicy;
use App\Policies\AccountReceivablePolicy;
use App\Policies\AnomalyPolicy;
use App\Policies\AnomalyRulePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BranchPolicy;
use App\Policies\BrandPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\BusinessGoalPolicy;
use App\Policies\CashMovementPolicy;
use App\Policies\CashRegisterPolicy;
use App\Policies\CashSessionPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerAddressPolicy;
use App\Policies\DispatchPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\InventoryAdjustmentPolicy;
use App\Policies\InventoryMovementPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\KpiSnapshotPolicy;
use App\Policies\PhysicalCountPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReconciliationRunPolicy;
use App\Policies\ReportDefinitionPolicy;
use App\Policies\SalePolicy;
use App\Policies\SalesReturnPolicy;
use App\Policies\StockLevelPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UnitOfMeasurePolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;


final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerPasswordPolicy();
        $this->registerAuthorizationPolicies();
        $this->registerRateLimiters();
        $this->registerObservers();
    }

    // Desacopla el identificador persistido del nombre de la clase PHP.
    private function registerMorphMap(): void
    {
        Relation::enforceMorphMap(
            (array) config('gintly.audit.morph_map', [])
        );
    }

    // Política de contraseñas en un punto único (RF-01-02).
    private function registerPasswordPolicy(): void
    {
        Password::defaults(
            fn (): Password => Password::min(12)
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
        );
    }

    //Registro explícito de las Policies.
    private function registerAuthorizationPolicies(): void
    {
        // MOD-01
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Business::class, BusinessPolicy::class);

        // MOD-02
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        // MOD-03
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(PhysicalCount::class, PhysicalCountPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(InventoryAdjustment::class, InventoryAdjustmentPolicy::class);
        Gate::policy(InventoryMovement::class, InventoryMovementPolicy::class);
  
        // MOD-04
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(AccountPayable::class, AccountPayablePolicy::class);

        // MOD-05
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);

        // MOD-06
        Gate::policy(CashRegister::class, CashRegisterPolicy::class);
        Gate::policy(CashSession::class, CashSessionPolicy::class);
        Gate::policy(CashMovement::class, CashMovementPolicy::class);

        // MOD-07
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        // MOD-08
        Gate::policy(AccountReceivable::class, AccountReceivablePolicy::class);

        // MOD-09
        Gate::policy(Dispatch::class, DispatchPolicy::class);

        // MOD-10
        Gate::policy(SalesReturn::class, SalesReturnPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);

        // MOD-11
        Gate::policy(AnomalyRule::class, AnomalyRulePolicy::class);
        Gate::policy(Anomaly::class, AnomalyPolicy::class);
        Gate::policy(ReconciliationRun::class, ReconciliationRunPolicy::class);

        // MOD-12
        Gate::policy(BusinessGoal::class, BusinessGoalPolicy::class);
        Gate::policy(KpiSnapshot::class, KpiSnapshotPolicy::class);
        Gate::policy(ReportDefinition::class, ReportDefinitionPolicy::class);
    }

    // Bloqueo por intentos fallidos mediante limitación de tasa.
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = mb_strtolower(trim((string) $request->input('business_slug')))
                .'|'.mb_strtolower(trim((string) $request->input('email')))
                .'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }

    // Registro explícito de Observers del sistema.
    private function registerObservers(): void
    {
        Business::observe(BusinessObserver::class);
    }
}
