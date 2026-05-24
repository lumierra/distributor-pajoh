<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\CustomerGeoController;
use App\Http\Controllers\Web\CustomerPhotoController;
use App\Http\Controllers\Web\CustomerTypeController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\DriverDocumentController;
use App\Http\Controllers\Web\GoodsReceiptController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\LoginHistoryController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\PriceTierController;
use App\Http\Controllers\Web\ProductCategoryController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductPriceController;
use App\Http\Controllers\Web\ProductSupplierController;
use App\Http\Controllers\Web\ProductUnitController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SalesOrderController;
use App\Http\Controllers\Web\StockLedgerController;
use App\Http\Controllers\Web\SupplierBankAccountController;
use App\Http\Controllers\Web\SupplierCategoryController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\SupplierDocumentController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\VehicleController;
use App\Http\Controllers\Web\VehicleDocumentController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/profile/change-password', [ChangePasswordController::class, 'show'])
        ->name('password.change.show');
    Route::post('/profile/change-password', [ChangePasswordController::class, 'store'])
        ->name('password.change.store');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'appName' => config('app.name'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    })->name('dashboard');

    // ── Profile (self) ────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ── Users ─────────────────────────────────────────────────────────
    Route::middleware('menu:master.user')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show']);

        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
        Route::post('users/{user}/force-logout', [UserController::class, 'forceLogout'])
            ->name('users.force-logout');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        Route::get('users/{user}/menu-overrides', [UserController::class, 'menuOverrides'])
            ->name('users.menu-overrides');
        Route::put('users/{user}/menu-overrides', [UserController::class, 'updateMenuOverrides'])
            ->name('users.menu-overrides.update');
    });

    // ── Roles ─────────────────────────────────────────────────────────
    Route::middleware('menu:master.role')->group(function (): void {
        Route::resource('roles', RoleController::class)->except(['show']);

        Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])
            ->name('roles.permissions.edit');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->name('roles.permissions.update');
    });

    // ── Menus ─────────────────────────────────────────────────────────
    Route::middleware('menu:master.menu')->group(function (): void {
        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
    });

    // ── Audit / Login History ─────────────────────────────────────────
    Route::middleware('menu:audit.login_history')->group(function (): void {
        Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');
    });

    // ── Supplier ──────────────────────────────────────────────────────
    Route::middleware('menu:master.supplier')->group(function (): void {
        Route::resource('suppliers', SupplierController::class)->except(['create', 'edit']);
        Route::post('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])
            ->name('suppliers.toggle-active');
        Route::get('api/internal/suppliers', [SupplierController::class, 'apiList'])
            ->name('api.suppliers.list');

        // Supplier Bank Accounts (nested)
        Route::post('suppliers/{supplier}/bank-accounts', [SupplierBankAccountController::class, 'store'])
            ->name('suppliers.bank-accounts.store');
        Route::put('supplier-bank-accounts/{bank_account}', [SupplierBankAccountController::class, 'update'])
            ->name('supplier-bank-accounts.update');
        Route::delete('supplier-bank-accounts/{bank_account}', [SupplierBankAccountController::class, 'destroy'])
            ->name('supplier-bank-accounts.destroy');
        Route::post('supplier-bank-accounts/{bank_account}/set-default', [SupplierBankAccountController::class, 'setDefault'])
            ->name('supplier-bank-accounts.set-default');

        // Supplier Documents (nested)
        Route::post('suppliers/{supplier}/documents', [SupplierDocumentController::class, 'store'])
            ->name('suppliers.documents.store');
        Route::get('supplier-documents/{document}', [SupplierDocumentController::class, 'show'])
            ->name('supplier-documents.show');
        Route::delete('supplier-documents/{document}', [SupplierDocumentController::class, 'destroy'])
            ->name('supplier-documents.destroy');

        // Supplier Categories
        Route::get('supplier-categories', [SupplierCategoryController::class, 'index'])
            ->name('supplier-categories.index');
        Route::post('supplier-categories', [SupplierCategoryController::class, 'store'])
            ->name('supplier-categories.store');
        Route::put('supplier-categories/{supplier_category}', [SupplierCategoryController::class, 'update'])
            ->name('supplier-categories.update');
        Route::delete('supplier-categories/{supplier_category}', [SupplierCategoryController::class, 'destroy'])
            ->name('supplier-categories.destroy');
    });

    // ── Product ───────────────────────────────────────────────────────
    Route::middleware('menu:master.product')->group(function (): void {
        Route::resource('products', ProductController::class)->except(['create', 'edit']);
        Route::post('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
            ->name('products.toggle-active');

        // Product Units (nested)
        Route::post('products/{product}/units', [ProductUnitController::class, 'store'])
            ->name('products.units.store');
        Route::delete('product-units/{unit}', [ProductUnitController::class, 'destroy'])
            ->name('product-units.destroy');

        // Product Prices (matrix update)
        Route::put('products/{product}/prices', [ProductPriceController::class, 'update'])
            ->name('products.prices.update');

        // Product ↔ Supplier (M2M)
        Route::post('products/{product}/suppliers', [ProductSupplierController::class, 'store'])
            ->name('products.suppliers.store');
        Route::put('supplier-products/{supplierProduct}', [ProductSupplierController::class, 'update'])
            ->name('supplier-products.update');
        Route::delete('supplier-products/{supplierProduct}', [ProductSupplierController::class, 'destroy'])
            ->name('supplier-products.destroy');

        // Product Categories
        Route::get('product-categories', [ProductCategoryController::class, 'index'])
            ->name('product-categories.index');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])
            ->name('product-categories.store');
        Route::put('product-categories/{productCategory}', [ProductCategoryController::class, 'update'])
            ->name('product-categories.update');
        Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])
            ->name('product-categories.destroy');
    });

    // ── Price Tier ────────────────────────────────────────────────────
    Route::middleware('menu:master.price_tier')->group(function (): void {
        Route::get('price-tiers', [PriceTierController::class, 'index'])->name('price-tiers.index');
        Route::post('price-tiers', [PriceTierController::class, 'store'])->name('price-tiers.store');
        Route::put('price-tiers/{priceTier}', [PriceTierController::class, 'update'])->name('price-tiers.update');
        Route::delete('price-tiers/{priceTier}', [PriceTierController::class, 'destroy'])->name('price-tiers.destroy');
    });

    // ── Customer ──────────────────────────────────────────────────────
    Route::middleware('menu:master.customer')->group(function (): void {
        Route::resource('customers', CustomerController::class)->except(['create', 'edit']);
        Route::post('customers/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])
            ->name('customers.toggle-active');
        Route::post('customers/{customer}/reassign-sales', [CustomerController::class, 'reassignSales'])
            ->name('customers.reassign-sales');
        Route::get('customers/{customer}/outstanding', [CustomerController::class, 'outstanding'])
            ->name('customers.outstanding');

        // Geo
        Route::put('customers/{customer}/geo', [CustomerGeoController::class, 'update'])
            ->name('customers.geo.update');
        Route::post('customers/{customer}/geo-pending/{pending}/approve', [CustomerGeoController::class, 'approvePending'])
            ->name('customers.geo-pending.approve');
        Route::post('customers/{customer}/geo-pending/{pending}/reject', [CustomerGeoController::class, 'rejectPending'])
            ->name('customers.geo-pending.reject');

        // Photos
        Route::post('customers/{customer}/photos', [CustomerPhotoController::class, 'store'])
            ->name('customers.photos.store');
        Route::delete('customer-photos/{photo}', [CustomerPhotoController::class, 'destroy'])
            ->name('customer-photos.destroy');

        // Customer Types
        Route::get('customer-types', [CustomerTypeController::class, 'index'])
            ->name('customer-types.index');
        Route::post('customer-types', [CustomerTypeController::class, 'store'])
            ->name('customer-types.store');
        Route::put('customer-types/{customerType}', [CustomerTypeController::class, 'update'])
            ->name('customer-types.update');
        Route::delete('customer-types/{customerType}', [CustomerTypeController::class, 'destroy'])
            ->name('customer-types.destroy');
    });

    // ── Driver ────────────────────────────────────────────────────────
    Route::middleware('menu:master.driver')->group(function (): void {
        Route::resource('drivers', DriverController::class)->except(['create', 'edit']);
        Route::post('drivers/{driver}/toggle-active', [DriverController::class, 'toggleActive'])
            ->name('drivers.toggle-active');
        Route::post('drivers/{driver}/set-unavailable', [DriverController::class, 'setUnavailable'])
            ->name('drivers.set-unavailable');

        Route::post('drivers/{driver}/documents', [DriverDocumentController::class, 'store'])
            ->name('drivers.documents.store');
        Route::get('driver-documents/{document}', [DriverDocumentController::class, 'show'])
            ->name('driver-documents.show');
        Route::delete('driver-documents/{document}', [DriverDocumentController::class, 'destroy'])
            ->name('driver-documents.destroy');
    });

    // ── Vehicle ───────────────────────────────────────────────────────
    Route::middleware('menu:master.vehicle')->group(function (): void {
        Route::resource('vehicles', VehicleController::class)->except(['create', 'edit']);
        Route::post('vehicles/{vehicle}/toggle-active', [VehicleController::class, 'toggleActive'])
            ->name('vehicles.toggle-active');
        Route::post('vehicles/{vehicle}/maintenance', [VehicleController::class, 'setMaintenance'])
            ->name('vehicles.maintenance.set');
        Route::delete('vehicles/{vehicle}/maintenance', [VehicleController::class, 'unsetMaintenance'])
            ->name('vehicles.maintenance.unset');

        Route::post('vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'store'])
            ->name('vehicles.documents.store');
        Route::get('vehicle-documents/{document}', [VehicleDocumentController::class, 'show'])
            ->name('vehicle-documents.show');
        Route::delete('vehicle-documents/{document}', [VehicleDocumentController::class, 'destroy'])
            ->name('vehicle-documents.destroy');
    });

    // ── Inventory (stocks & ledger viewer, MVP read-only) ─────────────
    Route::middleware('menu:inventory.stock')->group(function (): void {
        Route::get('stocks', [InventoryController::class, 'index'])->name('stocks.index');
        Route::get('stocks/{product}', [InventoryController::class, 'show'])->name('stocks.show');
    });
    Route::middleware('menu:inventory.ledger')->group(function (): void {
        Route::get('stock-ledger', [StockLedgerController::class, 'index'])->name('stock-ledger.index');
    });

    // ── Purchase Order ────────────────────────────────────────────────
    Route::middleware('menu:purchasing.po')->group(function (): void {
        Route::get('purchase-orders/suppliers/{supplier}/products', [PurchaseOrderController::class, 'productsForSupplier'])
            ->name('purchase-orders.supplier-products');
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('purchase-orders.cancel');
        Route::post('purchase-orders/{purchase_order}/close', [PurchaseOrderController::class, 'close'])
            ->name('purchase-orders.close');
        Route::get('purchase-orders/{purchase_order}/pdf', [PurchaseOrderController::class, 'downloadPdf'])
            ->name('purchase-orders.pdf');
        Route::post('purchase-orders/{purchase_order}/regenerate-pdf', [PurchaseOrderController::class, 'regeneratePdf'])
            ->name('purchase-orders.regenerate-pdf');
    });

    // ── Goods Receipt (GRN) ───────────────────────────────────────────
    Route::middleware('menu:purchasing.grn')->group(function (): void {
        Route::get('grns/po/{purchase_order}/details', [GoodsReceiptController::class, 'poDetails'])
            ->name('grns.po-details');
        Route::resource('grns', GoodsReceiptController::class)
            ->parameters(['grns' => 'goods_receipt']);
        Route::post('grns/{goods_receipt}/submit', [GoodsReceiptController::class, 'submit'])
            ->name('grns.submit');
        Route::post('grns/{goods_receipt}/cancel', [GoodsReceiptController::class, 'cancel'])
            ->name('grns.cancel');
        Route::post('grns/{goods_receipt}/reject', [GoodsReceiptController::class, 'reject'])
            ->name('grns.reject');
        Route::post('grns/{goods_receipt}/post', [GoodsReceiptController::class, 'post'])
            ->name('grns.post');
        Route::get('grns/{goods_receipt}/pdf', [GoodsReceiptController::class, 'downloadPdf'])
            ->name('grns.pdf');
    });

    // ── Sales Order ───────────────────────────────────────────────────
    Route::middleware('menu:sales.so')->group(function (): void {
        Route::get('sales-orders/customers/{customer}/products', [SalesOrderController::class, 'productPrices'])
            ->name('sales-orders.customer-products');
        Route::resource('sales-orders', SalesOrderController::class);
        Route::post('sales-orders/{sales_order}/submit', [SalesOrderController::class, 'submit'])
            ->name('sales-orders.submit');
        Route::post('sales-orders/{sales_order}/approve', [SalesOrderController::class, 'approve'])
            ->name('sales-orders.approve');
        Route::post('sales-orders/{sales_order}/approve-override', [SalesOrderController::class, 'approveOverride'])
            ->name('sales-orders.approve-override');
        Route::post('sales-orders/{sales_order}/reject', [SalesOrderController::class, 'reject'])
            ->name('sales-orders.reject');
        Route::post('sales-orders/{sales_order}/cancel', [SalesOrderController::class, 'cancel'])
            ->name('sales-orders.cancel');
    });
});
