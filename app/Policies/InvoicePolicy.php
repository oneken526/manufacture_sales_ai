<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * 【機能概要】: 請求書（Invoice）に対するロールベースのアクセス制御を定義するPolicy雛形
 * 【実装方針】: Invoiceモデルは後続タスク（TASK-0005〜0007）で実装されるため、
 *              本タスクでは「ACCOUNTING・ADMINロールのみ操作可能」という基盤ロジックのみを用意する
 * 【テスト対応】: TASK-0003 実装詳細3「Gate/Policyによる役割別アクセス制御基盤」の雛形要求に対応
 * 🟡 信頼性レベル: TASK-0003.md「InvoicePolicyの雛形を作成する」という要求からの妥当な推測（具体的なメソッド構成はEloquentの標準的なPolicy命名規則に基づく）
 */
class InvoicePolicy
{
    /**
     * 【処理内容】: 請求書一覧の閲覧可否を判定する
     */
    public function viewAny(User $user): bool
    {
        return $this->isAccountingOrAdmin($user);
    }

    /**
     * 【処理内容】: 個別の請求書閲覧可否を判定する
     */
    public function view(User $user): bool
    {
        return $this->isAccountingOrAdmin($user);
    }

    /**
     * 【処理内容】: 請求書の新規作成可否を判定する
     */
    public function create(User $user): bool
    {
        return $this->isAccountingOrAdmin($user);
    }

    /**
     * 【処理内容】: 請求書の更新可否を判定する
     */
    public function update(User $user): bool
    {
        return $this->isAccountingOrAdmin($user);
    }

    /**
     * 【共通判定ロジック】: REQ-064「管理職・経理担当者のみ請求書発行・入金確認可」に基づき、
     *                     ACCOUNTING・ADMINロールのみを許可対象とする
     */
    private function isAccountingOrAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::ACCOUNTING) || $user->hasRole(UserRole::ADMIN);
    }
}
