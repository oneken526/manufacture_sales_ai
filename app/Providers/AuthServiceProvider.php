<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * 【機能概要】: 認証・認可基盤に関するGate定義を行うサービスプロバイダ
 * 【実装方針】: TASK-0003で要求される `manage-invoices` Gateをここで一元的に定義する
 * 【テスト対応】: tests/Feature/Authorization/InvoiceGateTest.php の4テストを通すための実装
 * 🔵 信頼性レベル: TASK-0003.md 実装詳細3「Gate::define('manage-invoices', fn(User $user) => in_array($user->role, [UserRole::ACCOUNTING, UserRole::ADMIN]))」の例示に直接基づく
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * 【処理内容】: アプリケーション起動時に各種認可Gateを登録する
     */
    public function boot(): void
    {
        // 【Gate定義】: REQ-064「管理職・経理担当者のみ請求書発行・入金確認可」に基づき、
        //              ACCOUNTING・ADMINロールのユーザーのみ請求書操作を許可する
        Gate::define('manage-invoices', function (User $user): bool {
            return in_array($user->role, [UserRole::ACCOUNTING, UserRole::ADMIN], true);
        });
    }
}
