<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\User;
use App\Observers\BusinessObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\BranchPolicy;
use App\Policies\BrandPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\AccountReceivablePolicy;
use App\Policies\DispatchPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\SalePolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Business::class, BusinessPolicy::class);
        // MOD-02
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        // MOD-02
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
        //MOD-07
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        //mod-08
        Gate::policy(AccountReceivable::class, AccountReceivablePolicy::class);

        // MOD-09
        Gate::policy(Dispatch::class, DispatchPolicy::class);
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
