<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-0003 実装詳細3（Gate/Policyによる役割別アクセス制御基盤）に対応するテスト。
 * 対象テストケース: TC-N-03（auth-rbac-testcases.md）
 *
 * 'manage-invoices' Gate は未定義のため、本テストは現時点で失敗する
 * （Gate::allows()が常にfalseを返す、またはGate未定義の警告が発生する想定）。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md
 */
class InvoiceGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function allowedRoleProvider(): array
    {
        // 【テストデータ準備】: REQ-064「管理職・経理担当者のみ請求書発行・入金確認可」の許可対象ロールを用意する
        // 【境界値選択の根拠】: ACCOUNTINGに加え、システム全体の管理権限を持つADMINも許可対象に含まれる点を明示的に検証する
        return [
            'accounting（管理職・経理担当）' => [UserRole::ACCOUNTING],
            'admin（システム管理者）' => [UserRole::ADMIN],
        ];
    }

    #[DataProvider('allowedRoleProvider')]
    public function test_accounting_and_admin_roles_are_allowed_to_manage_invoices(UserRole $role): void
    {
        // 【テスト目的】: 'manage-invoices' Gateが、ACCOUNTING・ADMINロールのユーザーに対してtrueを返すことを確認する
        // 【テスト内容】: 各ロールのユーザーで Gate::forUser($user)->allows('manage-invoices') を呼び出す
        // 【期待される動作】: REQ-064が定める許可対象ロール（accounting, admin）はいずれもtrueとなる
        // 🔵 信頼性レベル: TASK-0003.md 実装詳細3「Gate::define('manage-invoices', fn(User $user) => in_array($user->role, [UserRole::ACCOUNTING, UserRole::ADMIN]))」の例示に直接基づく

        // 【テストデータ準備】: 許可対象ロールを持つユーザーを生成する
        // 【初期条件設定】: is_active=true（有効なユーザー）として用意する
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        // 【実際の処理実行】: 対象ユーザーに対して 'manage-invoices' アビリティの可否を判定する
        // 【処理内容】: Gate::forUser()で対象ユーザーを束縛し、allows()でアビリティ判定結果を取得する
        $allowed = Gate::forUser($user)->allows('manage-invoices');

        // 【結果検証】: 判定結果がtrueであることを確認する
        // 【期待値確認】: REQ-064の許可対象ロールであるため、Gateはtrueを返すはず
        $this->assertTrue($allowed); // 【確認内容】: ACCOUNTING・ADMINロールが請求書管理操作を許可されることを確認 🔵
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function disallowedRoleProvider(): array
    {
        // 【テストデータ準備】: REQ-003「在庫出荷担当者は請求書操作不可」の対象ロールに加え、SALESも非対象であることを確認する
        return [
            'warehouse（在庫・出荷担当者）' => [UserRole::WAREHOUSE],
            'sales（営業担当者）' => [UserRole::SALES],
        ];
    }

    #[DataProvider('disallowedRoleProvider')]
    public function test_warehouse_and_sales_roles_are_not_allowed_to_manage_invoices(UserRole $role): void
    {
        // 【テスト目的】: 'manage-invoices' Gateが、WAREHOUSE・SALESロールのユーザーに対してfalseを返すことを確認する
        // 【テスト内容】: 各ロールのユーザーで Gate::forUser($user)->allows('manage-invoices') を呼び出す
        // 【期待される動作】: REQ-003・REQ-064が定める非許可ロール（warehouse, sales）はいずれもfalseとなる
        // 🔵 信頼性レベル: TASK-0003.md REQ-003「在庫・出荷担当者が請求書操作を実行しようとした場合、システムはアクセスを拒否しなければならない」に直接基づく

        // 【テストデータ準備】: 請求書操作の許可対象に含まれないロールを持つユーザーを生成する
        // 【初期条件設定】: is_active=true（有効なユーザーであっても、ロールにより拒否されることを確認する）
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        // 【実際の処理実行】: 対象ユーザーに対して 'manage-invoices' アビリティの可否を判定する
        // 【処理内容】: 有効なユーザーであっても、ロールに基づき拒否されることを確認する
        $allowed = Gate::forUser($user)->allows('manage-invoices');

        // 【結果検証】: 判定結果がfalseであることを確認する
        // 【期待値確認】: REQ-003・REQ-064の非許可対象ロールであるため、Gateはfalseを返すはず
        $this->assertFalse($allowed); // 【確認内容】: WAREHOUSE・SALESロールが請求書管理操作を許可されないことを確認 🔵
    }
}
