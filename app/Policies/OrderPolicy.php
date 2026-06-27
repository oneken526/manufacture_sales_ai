<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SalesOrder;
use App\Models\User;

/**
 * 受注に対する認可ポリシー
 * 🟡 信頼性: REQ-042（要件定義書で🟡 業務フロー管理から推測）・api-endpoints.md「PUT /orders/{order}: 権限=admin」より
 */
class OrderPolicy
{
    /**
     * 受注を更新できるかを判定する（adminロールのみ許可）
     *
     * 受注確定後はシステム管理者のみが内容を編集できる（REQ-042）。
     * 🟡 信頼性: REQ-042「受注確定後はシステム管理者のみが内容編集できなければならない」（要件定義書で🟡）より
     */
    public function update(User $user, SalesOrder $order): bool
    {
        // 【認可判定】: admin ロールのユーザーのみ受注編集を許可する
        return $user->role === UserRole::ADMIN;
    }
}
