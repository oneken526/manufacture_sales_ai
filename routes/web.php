<?php

use App\Http\Controllers\ProfileController;
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

require __DIR__.'/auth.php';
