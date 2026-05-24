<?php

namespace App\Providers;

use App\Models\CompanyBankAccount;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\CustomerReturn;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\InvoiceExtensionLog;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductPrice;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\SupplierProduct;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Models\Vehicle;
use App\Observers\CompanyBankAccountObserver;
use App\Observers\CustomerObserver;
use App\Observers\DeliveryOrderObserver;
use App\Observers\DriverObserver;
use App\Observers\GoodsReceiptObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\PaymentRequestObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductPriceObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\SalesOrderObserver;
use App\Observers\SettingObserver;
use App\Observers\SupplierObserver;
use App\Observers\SupplierProductObserver;
use App\Observers\VehicleObserver;
use App\Policies\CreditNotePolicy;
use App\Policies\CustomerPhotoPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerReturnPolicy;
use App\Policies\CustomerTypePolicy;
use App\Policies\DeliveryOrderPolicy;
use App\Policies\DriverPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\InvoiceExtensionLogPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MenuPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentRequestPolicy;
use App\Policies\PriceTierPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductGroupPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SupplierCategoryPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\SupplierReturnPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use App\Services\Audit\ActivityLogger;
use App\Services\Notification\WhatsAppService;
use App\Services\Setting\SettingManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingManager::class);
        // ActivityLogger & WhatsAppService depend on per-request state
        // (current Request, auth() user) so we resolve them per use, not
        // as singletons.
    }

    public function boot(): void
    {
        Setting::observe(SettingObserver::class);
        CompanyBankAccount::observe(CompanyBankAccountObserver::class);
        Supplier::observe(SupplierObserver::class);
        Product::observe(ProductObserver::class);
        ProductPrice::observe(ProductPriceObserver::class);
        SupplierProduct::observe(SupplierProductObserver::class);
        Customer::observe(CustomerObserver::class);
        Driver::observe(DriverObserver::class);
        Vehicle::observe(VehicleObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        GoodsReceipt::observe(GoodsReceiptObserver::class);
        SalesOrder::observe(SalesOrderObserver::class);
        DeliveryOrder::observe(DeliveryOrderObserver::class);
        Invoice::observe(InvoiceObserver::class);
        PaymentRequest::observe(PaymentRequestObserver::class);
        Payment::observe(PaymentObserver::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(SupplierCategory::class, SupplierCategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(PriceTier::class, PriceTierPolicy::class);
        Gate::policy(ProductGroup::class, ProductGroupPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerType::class, CustomerTypePolicy::class);
        Gate::policy(CustomerPhoto::class, CustomerPhotoPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(DeliveryOrder::class, DeliveryOrderPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(InvoiceExtensionLog::class, InvoiceExtensionLogPolicy::class);
        Gate::policy(PaymentRequest::class, PaymentRequestPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(CustomerReturn::class, CustomerReturnPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(SupplierReturn::class, SupplierReturnPolicy::class);

        // NB: Superadmin bypass diatur per-policy via method `before()` di masing-masing
        // policy. Tidak pakai global `Gate::before` agar kasus self-action seperti
        // "hapus diri sendiri" tetap bisa difilter di policy (lihat UserPolicy::delete).

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
