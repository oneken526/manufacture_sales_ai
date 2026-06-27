<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\StockAvailabilityController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShipmentController;
use App\Services\PdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// TASK-0001 動作確認用: mPDFによる日本語PDF生成確認（確認後に削除すること）
Route::get('/dev/pdf-sample', function (PdfService $pdf) {
    return $pdf->download('pdf.sample', [
        'issuedAt' => now()->format('Y年m月d日'),
    ], 'sample.pdf');
})->name('dev.pdf-sample');

// TASK-0001 動作確認用: jQuery・Bootstrap 5 読み込み確認（確認後に削除すること）
Route::get('/dev/frontend-check', function () {
    return view('dev.frontend-check');
})->name('dev.frontend-check');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// TASK-0003 ロール別ログインリダイレクト先（暫定画面）。
// 後続タスク（TASK-0005〜0007）で実画面に置き換え予定。
Route::get('/admin/dashboard', function () {
    return view('admin.dashboard');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.dashboard');

Route::get('/sales/dashboard', function () {
    return view('sales.dashboard');
})->middleware(['auth', 'verified', 'role:sales'])->name('sales.dashboard');

Route::get('/warehouse/dashboard', function () {
    return view('warehouse.dashboard');
})->middleware(['auth', 'verified', 'role:warehouse'])->name('warehouse.dashboard');

Route::get('/accounting/dashboard', function () {
    return view('accounting.dashboard');
})->middleware(['auth', 'verified', 'role:accounting'])->name('accounting.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 顧客マスタ管理（TASK-0005）
// 🔵 信頼性: api-endpoints.md（顧客管理エンドポイント群）・NFR-021・REQ-010〜REQ-013より
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:sales,accounting,admin')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('role:sales,admin')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });
});

// 内部AJAX用エンドポイント（TASK-0005, TASK-0015）
// 🔵 信頼性: api-endpoints.md「内部APIルート（jQuery AJAX用 /api/internal/*）」より
Route::middleware(['auth', 'verified', 'role:sales,accounting,admin'])->group(function () {
    // 顧客インクリメンタルサーチ
    Route::get('/api/internal/customers/search', [CustomerController::class, 'searchJson'])->name('api.internal.customers.search');
    // 製品インクリメンタルサーチ（TASK-0015）
    Route::get('/api/internal/products/search', [ProductController::class, 'searchJson'])->name('api.internal.products.search');
    // 利用可能在庫チェック（TASK-0015）
    Route::get('/api/internal/products/{product}/availability', [StockAvailabilityController::class, 'show'])->name('api.internal.products.availability');
});

// 内部AJAX用エンドポイント（見積明細の金額リアルタイム計算、TASK-0008）
// 🟡 信頼性: api-endpoints.md「POST /api/internal/quotations/calculate」より
Route::middleware(['auth', 'verified', 'role:sales,admin'])->group(function () {
    Route::post('/api/internal/quotations/calculate', [QuotationController::class, 'calculate'])->name('api.internal.quotations.calculate');
});

// 製品マスタ管理（TASK-0006）
// 🔵 信頼性: api-endpoints.md（製品管理エンドポイント群）・REQ-020〜REQ-023, REQ-072より
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:sales,accounting,warehouse,admin')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    });

    Route::middleware('role:warehouse,admin')->group(function () {
        Route::get('/products/{product}/adjust-stock', [ProductController::class, 'adjustStockForm'])->name('products.adjust-stock.form');
        Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
    });
});

// 見積管理（TASK-0008）
// 🔵 信頼性: TASK-0008.md実装詳細6（QuotationController: index/create/store/show/pdf/confirm）・REQ-030〜REQ-033より
Route::middleware(['auth', 'verified', 'role:sales,admin'])->group(function () {
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('/quotations/{quotation}/confirm', [QuotationController::class, 'confirm'])->name('quotations.confirm');
});

// 受注管理（TASK-0009）
// 🔵 信頼性: TASK-0009.md実装詳細2（OrderController: index/show/edit/update/cancel/issueShippingInstruction）・api-endpoints.mdより
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:sales,accounting,admin')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    });

    Route::middleware('role:sales,admin')->group(function () {
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/shipping-instruction', [OrderController::class, 'issueShippingInstruction'])->name('orders.shipping_instruction');
    });
});

// 出荷管理（TASK-0010）
// 🔵 信頼性: api-endpoints.md（出荷管理セクション）・REQ-003より
Route::middleware(['auth', 'verified'])->group(function () {
    // 出荷指示一覧・出荷完了・返品: warehouse/admin
    Route::middleware('role:warehouse,admin')->group(function () {
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::post('/shipments/{order}/complete', [ShipmentController::class, 'complete'])->name('shipments.complete');
        Route::post('/shipments/{shipment}/return', [ShipmentController::class, 'processReturn'])->name('shipments.return');
    });
    // 納品書ダウンロード: warehouse/sales/admin
    Route::middleware('role:warehouse,sales,admin')->group(function () {
        Route::get('/shipments/{shipment}/delivery-note', [ShipmentController::class, 'deliveryNote'])->name('shipments.delivery_note');
    });
});

// 在庫管理（TASK-0011）
// 🔵 信頼性: api-endpoints.md（GET /inventory, GET /inventory/{product}/movements）・REQ-070, REQ-072より
Route::middleware(['auth', 'verified', 'role:warehouse,admin'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{product}/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
});

// 請求書管理（TASK-0012）
// 🔵 REQ-064: accounting/adminのみ発行・入金確認可能、REQ-003: warehouseは請求書操作不可
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:accounting,admin')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices/{order}', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::put('/invoices/{invoice}/payment-status', [InvoiceController::class, 'updatePaymentStatus'])->name('invoices.payment_status');
    });
    // PDFダウンロードはsalesも許可
    Route::middleware('role:accounting,sales,admin')->group(function () {
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });
});

// 振込データCSVインポート（TASK-0013）
// 🔵 REQ-063: accounting/adminのみアクセス可能
Route::middleware(['auth', 'verified', 'role:accounting,admin'])->group(function () {
    Route::get('/payments/import', [PaymentController::class, 'index'])->name('payments.import');
    Route::post('/payments/import', [PaymentController::class, 'import'])->name('payments.import.store');
});

// 売上レポート（TASK-0014）
// 🔵 REQ-084: sales/accounting/adminがレポートを閲覧できること
Route::middleware(['auth', 'verified', 'role:sales,accounting,admin'])->group(function () {
    Route::get('/reports/sales', [ReportController::class, 'index'])->name('reports.sales');
    Route::get('/reports/sales/export', [ReportController::class, 'export'])->name('reports.sales.export');
});

// 管理者向けユーザー管理（TASK-0007）
// 🟡 信頼性: TASK-0007.md実装詳細1（ルーティングは`role:admin`でadminロールに限定する）より
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('admin.users.toggle-active');
    Route::post('/admin/users/{user}/send-password-reset', [UserController::class, 'sendPasswordResetLink'])->name('admin.users.send-password-reset');
});

require __DIR__.'/auth.php';
