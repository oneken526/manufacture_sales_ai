<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
