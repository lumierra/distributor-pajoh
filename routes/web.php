<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\AdjustmentController;
use App\Http\Controllers\Web\CompanyBankAccountController;
use App\Http\Controllers\Web\CompanySettingController;
use App\Http\Controllers\Web\CreditNoteController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\CustomerCreditLimitController;
use App\Http\Controllers\Web\CustomerGeoController;
use App\Http\Controllers\Web\CustomerImportController;
use App\Http\Controllers\Web\CustomerPhotoController;
use App\Http\Controllers\Web\CustomerPricePackageController;
use App\Http\Controllers\Web\CustomerReturnController;
use App\Http\Controllers\Web\CustomerTypeController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeliveryOrderController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\GoodsReceiptController;
use App\Http\Controllers\Web\GrnAttachmentController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\InvoiceController;
use App\Http\Controllers\Web\InvoiceExtensionController;
use App\Http\Controllers\Web\LoginHistoryController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\OpeningController;
use App\Http\Controllers\Web\OpnameController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PaymentRequestController;
use App\Http\Controllers\Web\ProductCategoryController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductGroupController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\Reports\ReportController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SalesOrderController;
use App\Http\Controllers\Web\SalesScheduleController;
use App\Http\Controllers\Web\SalesUserController;
use App\Http\Controllers\Web\SalesVisitBypassRequestController;
use App\Http\Controllers\Web\SalesVisitController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\StockLedgerController;
use App\Http\Controllers\Web\SupplierBankAccountController;
use App\Http\Controllers\Web\SupplierCategoryController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\SupplierDocumentController;
use App\Http\Controllers\Web\SupplierReturnController;
use App\Http\Controllers\Web\TrashController;
use App\Http\Controllers\Web\UnitController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\UserDeviceController;
use App\Http\Controllers\Web\VehicleController;
use App\Http\Controllers\Web\WaNotificationController;
use App\Http\Controllers\Web\YearEndClosingController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

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

        // Sales user — list & create terpisah dgn field tambahan + pre-register device
        Route::get('sales-users', [SalesUserController::class, 'index'])->name('sales-users.index');
        Route::post('sales-users', [SalesUserController::class, 'store'])->name('sales-users.store');
        Route::put('sales-users/{user}', [SalesUserController::class, 'update'])->name('sales-users.update');
        Route::delete('sales-users/{user}', [SalesUserController::class, 'destroy'])->name('sales-users.destroy');
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
        Route::post('products/bulk-destroy', [ProductController::class, 'bulkDestroy'])
            ->name('products.bulk-destroy');
        Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);
        Route::post('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
            ->name('products.toggle-active');
        Route::get('products/{product}/details', [ProductController::class, 'details'])
            ->name('products.details');
        // Satuan & paket harga produk dikelola langsung lewat form produk
        // (store/update), bukan endpoint granular terpisah lagi.

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

    // ── Unit (master satuan global) ───────────────────────────────────
    Route::middleware('menu:master.unit')->group(function (): void {
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    // ── Product Group ─────────────────────────────────────────────────
    Route::middleware('menu:master.product_group')->group(function (): void {
        Route::get('product-groups', [ProductGroupController::class, 'index'])
            ->name('product-groups.index');
        Route::get('product-groups/{productGroup}', [ProductGroupController::class, 'show'])
            ->name('product-groups.show');
        Route::post('product-groups', [ProductGroupController::class, 'store'])
            ->name('product-groups.store');
        Route::put('product-groups/{productGroup}', [ProductGroupController::class, 'update'])
            ->name('product-groups.update');
        Route::delete('product-groups/{productGroup}', [ProductGroupController::class, 'destroy'])
            ->name('product-groups.destroy');
        Route::put('product-groups/{productGroup}/products', [ProductGroupController::class, 'syncProducts'])
            ->name('product-groups.sync-products');
        Route::put('product-groups/{productGroup}/sales', [ProductGroupController::class, 'syncSales'])
            ->name('product-groups.sync-sales');
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

        // Credit Limit per Supplier
        Route::get('customers/{customer}/credit-limits', [CustomerCreditLimitController::class, 'index'])
            ->name('customers.credit-limits.index');
        Route::put('customers/{customer}/credit-limits', [CustomerCreditLimitController::class, 'sync'])
            ->name('customers.credit-limits.sync');

        // Paket Harga per Produk
        Route::get('customers/{customer}/price-packages', [CustomerPricePackageController::class, 'index'])
            ->name('customers.price-packages.index');
        Route::get('customers/{customer}/price-packages/search', [CustomerPricePackageController::class, 'search'])
            ->name('customers.price-packages.search');
        Route::put('customers/{customer}/price-packages', [CustomerPricePackageController::class, 'sync'])
            ->name('customers.price-packages.sync');

        // Bulk Import via Excel
        Route::get('customers/import/template', [CustomerImportController::class, 'downloadTemplate'])
            ->name('customers.import.template');
        Route::post('customers/import', [CustomerImportController::class, 'import'])
            ->name('customers.import');
        Route::get('customers/import/errors', [CustomerImportController::class, 'downloadErrors'])
            ->name('customers.import.errors');

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
        Route::resource('drivers', DriverController::class)->except(['create', 'edit', 'show']);
        Route::post('drivers/{driver}/toggle-active', [DriverController::class, 'toggleActive'])
            ->name('drivers.toggle-active');
        Route::post('drivers/{driver}/set-unavailable', [DriverController::class, 'setUnavailable'])
            ->name('drivers.set-unavailable');
    });

    // ── Vehicle ───────────────────────────────────────────────────────
    Route::middleware('menu:master.vehicle')->group(function (): void {
        Route::resource('vehicles', VehicleController::class)->except(['create', 'edit', 'show']);
        Route::post('vehicles/{vehicle}/toggle-active', [VehicleController::class, 'toggleActive'])
            ->name('vehicles.toggle-active');
        Route::post('vehicles/{vehicle}/maintenance', [VehicleController::class, 'setMaintenance'])
            ->name('vehicles.maintenance.set');
        Route::delete('vehicles/{vehicle}/maintenance', [VehicleController::class, 'unsetMaintenance'])
            ->name('vehicles.maintenance.unset');
    });

    // ── Inventory (stocks & ledger viewer, MVP read-only) ─────────────
    Route::middleware('menu:inventory.stock')->group(function (): void {
        Route::get('stocks', [InventoryController::class, 'index'])->name('stocks.index');
        Route::get('stocks/{product}', [InventoryController::class, 'show'])->name('stocks.show');
    });
    Route::middleware('menu:inventory.ledger')->group(function (): void {
        Route::get('stock-ledger', [StockLedgerController::class, 'index'])->name('stock-ledger.index');
    });

    // ── Stock Adjustment (penyesuaian stok manual) ────────────────────
    Route::middleware('menu:inventory.adjustment')->group(function (): void {
        Route::get('adjustments/stock-products', [AdjustmentController::class, 'stockProducts'])
            ->name('adjustments.stock-products');
    });

    // ── Stok Awal (opening balance) ───────────────────────────────────
    Route::middleware('menu:inventory.opening')->group(function (): void {
        Route::get('openings/picker-products', [OpeningController::class, 'pickerProducts'])
            ->name('openings.picker-products');
        Route::resource('openings', OpeningController::class)
            ->parameters(['openings' => 'opening'])
            ->except(['destroy']);
        Route::post('openings/{opening}/post', [OpeningController::class, 'post'])
            ->name('openings.post');
        Route::post('openings/{opening}/cancel', [OpeningController::class, 'cancel'])
            ->name('openings.cancel');
        Route::resource('adjustments', AdjustmentController::class)
            ->parameters(['adjustments' => 'adjustment'])
            ->except(['destroy']);
        Route::post('adjustments/{adjustment}/post', [AdjustmentController::class, 'post'])
            ->name('adjustments.post');
        Route::post('adjustments/{adjustment}/cancel', [AdjustmentController::class, 'cancel'])
            ->name('adjustments.cancel');
    });

    // ── Stock Opname (perhitungan fisik) ──────────────────────────────
    Route::middleware('menu:inventory.opname')->group(function (): void {
        Route::resource('opnames', OpnameController::class)
            ->parameters(['opnames' => 'opname'])
            ->except(['destroy']);
        Route::post('opnames/{opname}/post', [OpnameController::class, 'post'])
            ->name('opnames.post');
        Route::post('opnames/{opname}/cancel', [OpnameController::class, 'cancel'])
            ->name('opnames.cancel');
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
        Route::get('grns/suppliers/{supplier}/products', [GoodsReceiptController::class, 'supplierProducts'])
            ->name('grns.supplier-products');
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
        Route::post('grn-items/{grnItem}/settle-pending', [GoodsReceiptController::class, 'settleItemPending'])
            ->name('grn-items.settle-pending');
        Route::post('grn-items/{grnItem}/unsettle-pending', [GoodsReceiptController::class, 'unsettleItemPending'])
            ->name('grn-items.unsettle-pending');
        Route::get('grns/{goods_receipt}/pdf', [GoodsReceiptController::class, 'downloadPdf'])
            ->name('grns.pdf');

        // Lampiran surat penerimaan (multi-file).
        Route::post('grns/{goods_receipt}/attachments', [GrnAttachmentController::class, 'store'])
            ->name('grns.attachments.store');
        Route::get('grn-attachments/{grnAttachment}', [GrnAttachmentController::class, 'show'])
            ->name('grn-attachments.show');
        Route::delete('grn-attachments/{grnAttachment}', [GrnAttachmentController::class, 'destroy'])
            ->name('grn-attachments.destroy');
    });

    // ── Sales Order ───────────────────────────────────────────────────
    Route::middleware('menu:sales.so')->group(function (): void {
        Route::get('sales-orders/product-catalog', [SalesOrderController::class, 'productPrices'])
            ->name('sales-orders.product-catalog');
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

    // ── Delivery Order (Surat Jalan) ──────────────────────────────────
    Route::middleware('menu:sales.do')->group(function (): void {
        Route::get('delivery-orders/so/{sales_order}/details', [DeliveryOrderController::class, 'soDetails'])
            ->name('delivery-orders.so-details');
        Route::resource('delivery-orders', DeliveryOrderController::class);
        Route::post('delivery-orders/{delivery_order}/start-picking', [DeliveryOrderController::class, 'startPicking'])
            ->name('delivery-orders.start-picking');
        Route::post('delivery-orders/{delivery_order}/confirm-picks', [DeliveryOrderController::class, 'confirmPicks'])
            ->name('delivery-orders.confirm-picks');
        Route::post('delivery-orders/{delivery_order}/mark-packed', [DeliveryOrderController::class, 'markPacked'])
            ->name('delivery-orders.mark-packed');
        Route::post('delivery-orders/{delivery_order}/start-delivery', [DeliveryOrderController::class, 'startDelivery'])
            ->name('delivery-orders.start-delivery');
        Route::post('delivery-orders/{delivery_order}/mark-delivered', [DeliveryOrderController::class, 'markDelivered'])
            ->name('delivery-orders.mark-delivered');
        Route::post('delivery-orders/{delivery_order}/cancel', [DeliveryOrderController::class, 'cancel'])
            ->name('delivery-orders.cancel');
        Route::get('delivery-orders/{delivery_order}/pdf', [DeliveryOrderController::class, 'downloadPdf'])
            ->name('delivery-orders.pdf');
        Route::get('delivery-orders/{delivery_order}/escp', [DeliveryOrderController::class, 'printEscp'])
            ->name('delivery-orders.escp');
    });

    // ── Invoice (Faktur) ──────────────────────────────────────────────
    Route::middleware('menu:sales.invoice')->group(function (): void {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('invoices/mark-overdue', [InvoiceController::class, 'markOverdue'])
            ->name('invoices.mark-overdue');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
            ->name('invoices.pdf');
        Route::post('invoices/{invoice}/regenerate-pdf', [InvoiceController::class, 'regeneratePdf'])
            ->name('invoices.regenerate-pdf');

        // Invoice Extension request (sales → admin approve)
        Route::post('invoices/{invoice}/extensions', [InvoiceExtensionController::class, 'store'])
            ->name('invoices.extensions.store');
    });

    // ── Invoice Extensions (admin review list) ────────────────────────
    Route::middleware('menu:sales.invoice')->group(function (): void {
        Route::get('invoice-extensions', [InvoiceExtensionController::class, 'index'])
            ->name('invoice-extensions.index');
        Route::post('invoice-extensions/{invoice_extension_log}/approve', [InvoiceExtensionController::class, 'approve'])
            ->name('invoice-extensions.approve');
        Route::post('invoice-extensions/{invoice_extension_log}/reject', [InvoiceExtensionController::class, 'reject'])
            ->name('invoice-extensions.reject');
        Route::post('invoice-extensions/{invoice_extension_log}/cancel', [InvoiceExtensionController::class, 'cancel'])
            ->name('invoice-extensions.cancel');
    });

    // ── Payment Request (sales lapor → kasir verify) ──────────────────
    Route::middleware('menu:finance.payment_request')->group(function (): void {
        Route::resource('payment-requests', PaymentRequestController::class);
        Route::post('payment-requests/{payment_request}/submit', [PaymentRequestController::class, 'submit'])
            ->name('payment-requests.submit');
        Route::post('payment-requests/{payment_request}/cancel', [PaymentRequestController::class, 'cancel'])
            ->name('payment-requests.cancel');
        Route::post('payment-requests/{payment_request}/verify', [PaymentRequestController::class, 'verify'])
            ->name('payment-requests.verify');
        Route::post('payment-requests/{payment_request}/reject', [PaymentRequestController::class, 'reject'])
            ->name('payment-requests.reject');
    });

    // ── Payment (kasir) ───────────────────────────────────────────────
    Route::middleware('menu:finance.payment')->group(function (): void {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/clear-giro', [PaymentController::class, 'clearGiro'])
            ->name('payments.clear-giro');
        Route::post('payments/{payment}/bounce-giro', [PaymentController::class, 'bounceGiro'])
            ->name('payments.bounce-giro');
    });

    // ── Customer Return + Credit Note ─────────────────────────────────
    Route::middleware('menu:returns.customer')->group(function (): void {
        Route::resource('customer-returns', CustomerReturnController::class);
        Route::post('customer-returns/{customer_return}/sort', [CustomerReturnController::class, 'sort'])
            ->name('customer-returns.sort');
        Route::post('customer-returns/{customer_return}/post', [CustomerReturnController::class, 'post'])
            ->name('customer-returns.post');
        Route::post('customer-returns/{customer_return}/cancel', [CustomerReturnController::class, 'cancel'])
            ->name('customer-returns.cancel');
    });

    Route::middleware('menu:returns.credit_note')->group(function (): void {
        Route::get('credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('credit-notes/{credit_note}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
        Route::post('credit-notes/{credit_note}/apply', [CreditNoteController::class, 'apply'])->name('credit-notes.apply');
    });

    // ── Sales Schedule + Visit + Device (T10) ─────────────────────────
    Route::middleware('menu:sales.schedule')->group(function (): void {
        Route::resource('sales-schedules', SalesScheduleController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('menu:sales.visit')->group(function (): void {
        Route::get('sales-visits', [SalesVisitController::class, 'index'])->name('sales-visits.index');
        Route::get('sales-visits/{sales_visit}', [SalesVisitController::class, 'show'])->name('sales-visits.show');
        Route::post('sales-visits/{sales_visit}/cancel', [SalesVisitController::class, 'cancel'])->name('sales-visits.cancel');

        Route::get('sales-visit-bypass-requests', [SalesVisitBypassRequestController::class, 'index'])->name('sales-visit-bypass-requests.index');
        Route::post('sales-visit-bypass-requests/{sales_visit_bypass_request}/approve', [SalesVisitBypassRequestController::class, 'approve'])->name('sales-visit-bypass-requests.approve');
        Route::post('sales-visit-bypass-requests/{sales_visit_bypass_request}/reject', [SalesVisitBypassRequestController::class, 'reject'])->name('sales-visit-bypass-requests.reject');
    });

    Route::middleware('menu:master.user')->group(function (): void {
        Route::get('user-devices', [UserDeviceController::class, 'index'])->name('user-devices.index');
        Route::post('user-devices/pending/{user_device_pending_request}/approve', [UserDeviceController::class, 'approvePending'])->name('user-devices.approve-pending');
        Route::post('user-devices/pending/{user_device_pending_request}/reject', [UserDeviceController::class, 'rejectPending'])->name('user-devices.reject-pending');
        Route::post('user-devices/{user_device}/revoke', [UserDeviceController::class, 'revoke'])->name('user-devices.revoke');
    });

    // ── Activity Log + Trash (T19) ────────────────────────────────────
    Route::middleware('menu:audit.activity')->group(function (): void {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/for-model', [ActivityLogController::class, 'forModel'])->name('activity-logs.for-model');
        Route::get('activity-logs/{activity_log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    });

    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');

    // ── Setting: Profil Perusahaan (T01) ──────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('company', [CompanySettingController::class, 'edit'])->name('company');
        Route::post('company/profile', [CompanySettingController::class, 'updateProfile'])->name('company.profile.update');
        Route::post('company/invoice-text', [CompanySettingController::class, 'updateInvoiceText'])->name('company.invoice-text.update');
        Route::post('company/assets', [CompanySettingController::class, 'updateAssets'])->name('company.assets.update');

        // Pengaturan berbasis-key (schema-driven).
        Route::get('numbering', [SettingsController::class, 'numbering'])->name('numbering');
        Route::post('numbering', [SettingsController::class, 'updateNumbering']);
        Route::get('sales', [SettingsController::class, 'sales'])->name('sales');
        Route::post('sales', [SettingsController::class, 'updateSales']);
        Route::get('inventory', [SettingsController::class, 'inventory'])->name('inventory');
        Route::post('inventory', [SettingsController::class, 'updateInventory']);
        Route::get('system', [SettingsController::class, 'system'])->name('system');
        Route::post('system', [SettingsController::class, 'updateSystem']);

        // Log Sensitif (filter dari activity_logs).
        Route::get('sensitive-log', [SettingsController::class, 'sensitiveLog'])->name('sensitive-log');

        // Rekening Bank Perusahaan (CRUD).
        Route::get('bank-accounts', [CompanyBankAccountController::class, 'index'])->name('bank-accounts.index');
        Route::post('bank-accounts', [CompanyBankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::put('bank-accounts/{bankAccount}', [CompanyBankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('bank-accounts/{bankAccount}', [CompanyBankAccountController::class, 'destroy'])->name('bank-accounts.destroy');
    });

    // ── WhatsApp Notifications (T18) ──────────────────────────────────
    Route::prefix('wa')->name('wa.')->group(function (): void {
        Route::get('notifications', [WaNotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/{wa_notification}', [WaNotificationController::class, 'show'])->name('notifications.show');
        Route::post('notifications/{wa_notification}/retry', [WaNotificationController::class, 'retry'])->name('notifications.retry');
        Route::post('notifications/manual-send', [WaNotificationController::class, 'manualSend'])->name('notifications.manual-send');
        Route::get('test-connection', [WaNotificationController::class, 'testConnection'])->name('test-connection');
        Route::get('templates', [WaNotificationController::class, 'templatesIndex'])->name('templates.index');
        Route::put('templates/{wa_template}', [WaNotificationController::class, 'templatesUpdate'])->name('templates.update');
        Route::get('settings', [WaNotificationController::class, 'settingsIndex'])->name('settings.index');
        Route::post('settings', [WaNotificationController::class, 'settingsUpdate'])->name('settings.update');
    });

    // ── Year-End Closing (T20) ────────────────────────────────────────
    Route::prefix('year-end-closings')->name('year-end-closings.')->group(function (): void {
        Route::get('/', [YearEndClosingController::class, 'index'])->name('index');
        Route::get('pre-check/{fiscalYear}', [YearEndClosingController::class, 'preCheck'])->name('pre-check');
        Route::post('execute', [YearEndClosingController::class, 'execute'])->name('execute');
        Route::get('{year_end_closing}', [YearEndClosingController::class, 'show'])->name('show');
    });

    // ── Reports (T17) ─────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::middleware('menu:reports.sales')->get('sales', [ReportController::class, 'salesIndex'])->name('sales');
        Route::middleware('menu:reports.stock')->get('stock', [ReportController::class, 'stockIndex'])->name('stock');
        Route::middleware('menu:reports.ar_aging')->get('ar-aging', [ReportController::class, 'arAgingIndex'])->name('ar-aging');
        Route::middleware('menu:reports.margin')->get('margin', [ReportController::class, 'marginIndex'])->name('margin');
        Route::middleware('menu:reports.sales_activity')->get('sales-activity', [ReportController::class, 'salesActivityIndex'])->name('sales-activity');
        Route::post('regenerate', [ReportController::class, 'regenerate'])->name('regenerate');
        Route::get('export/{reportType}', [ReportController::class, 'export'])->name('export');

        // Drill-downs
        Route::middleware('menu:reports.sales')->get('sales/drill-down', [ReportController::class, 'drillDownSales'])->name('sales.drill-down');
        Route::middleware('menu:reports.stock')->get('stock/drill-down/{productId}/{batchId?}', [ReportController::class, 'drillDownStock'])->name('stock.drill-down');
        Route::middleware('menu:reports.ar_aging')->get('ar-aging/drill-down/{customerId}', [ReportController::class, 'drillDownArAging'])->name('ar-aging.drill-down');
    });

    // ── Supplier Return ───────────────────────────────────────────────
    Route::middleware('menu:returns.supplier')->group(function (): void {
        Route::resource('supplier-returns', SupplierReturnController::class);
        Route::post('supplier-returns/{supplier_return}/approve', [SupplierReturnController::class, 'approve'])
            ->name('supplier-returns.approve');
        Route::post('supplier-returns/{supplier_return}/mark-sent', [SupplierReturnController::class, 'markSent'])
            ->name('supplier-returns.mark-sent');
        Route::post('supplier-returns/{supplier_return}/settle', [SupplierReturnController::class, 'settle'])
            ->name('supplier-returns.settle');
        Route::post('supplier-returns/{supplier_return}/cancel', [SupplierReturnController::class, 'cancel'])
            ->name('supplier-returns.cancel');
    });
});
