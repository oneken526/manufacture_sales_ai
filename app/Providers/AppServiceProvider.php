<?php

namespace App\Providers;

use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\QuotationRepositoryInterface;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\EloquentSalesOrderRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\QuotationRepository;
use App\Models\SalesOrder;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Mpdf\Mpdf;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 【機能概要】: mPDFインスタンスをコンテナにバインドし、config/mpdf.phpの設定（日本語フォント等）を適用する
        // 【実装方針】: PdfServiceがコンテナ経由でMpdfを解決することで、テスト時に容易にモックへ差し替え可能にする
        // 【テスト対応】: PdfServiceIntegrationTest（$this->app->bind(Mpdf::class, ...)によるモック差し替え）に対応
        // 🟡 信頼性レベル: タスクファイル「呼び出し方法を切り替えやすい設計にしておく」という指示から、
        //                  Laravelの標準的な依存性注入パターンとして妥当に推測
        $this->app->bind(Mpdf::class, fn () => new Mpdf(config('mpdf')));

        // 【機能概要】: 顧客リポジトリのインターフェースをEloquent実装にバインドする
        // 【実装方針】: architecture.mdのRepositoryパターンに従い、Service層がインターフェースに依存することで
        //              テスト時にモック実装へ差し替え可能にする
        // 🔵 信頼性レベル: TASK-0005.md実装詳細1「CustomerRepositoryInterface」「CustomerRepository（Eloquent実装）」より
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);

        // 【機能概要】: 製品リポジトリのインターフェースをEloquent実装にバインドする
        // 【実装方針】: architecture.mdのRepositoryパターンに従い、Service層がインターフェースに依存することで
        //              テスト時にモック実装へ差し替え可能にする
        // 🔵 信頼性レベル: TASK-0006.md実装詳細1「ProductRepositoryInterface」「ProductRepository（Eloquent実装）」より
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);

        // 【機能概要】: 見積リポジトリのインターフェースをEloquent実装にバインドする
        // 【実装方針】: architecture.mdのRepositoryパターンに従い、Service層がインターフェースに依存することで
        //              テスト時にモック実装へ差し替え可能にする
        // 🔵 信頼性レベル: TASK-0008.md実装詳細1「QuotationRepositoryInterface」「QuotationRepository（Eloquent実装）」より
        $this->app->bind(QuotationRepositoryInterface::class, QuotationRepository::class);

        // 【機能概要】: 受注リポジトリのインターフェースをEloquent実装にバインドする
        // 🔵 信頼性: TASK-0009.md実装詳細1「SalesOrderRepositoryInterface + EloquentSalesOrderRepository」より
        $this->app->bind(SalesOrderRepositoryInterface::class, EloquentSalesOrderRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 【機能概要】: 受注ポリシーを SalesOrder モデルに紐付ける
        // 【実装方針】: OrderPolicy は SalesOrderPolicy の命名規則から外れるため手動登録する
        // 🟡 信頼性レベル: REQ-042・TASK-0009.md実装詳細3「OrderPolicy を AuthServiceProvider または AppServiceProvider に登録」より
        Gate::policy(SalesOrder::class, OrderPolicy::class);
    }
}
