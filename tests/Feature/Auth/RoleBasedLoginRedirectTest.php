<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0003 単体テスト要件 テストケース2 / 統合テスト要件2 に対応するテスト。
 * 対象テストケース: TC-N-01（auth-rbac-testcases.md）
 *
 * AuthenticatedSessionController::store() は現状 redirect()->intended(route('dashboard')) の
 * 固定リダイレクトであり、ロール別の分岐が存在しないため、本テストは現時点で失敗する
 * （4ロールすべてが同じリダイレクト先になってしまい、TC-A-01相当のwarehouse専用判定もできない）。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md
 */
class RoleBasedLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(UserRole $role): \Illuminate\Testing\TestResponse
    {
        // 【テストデータ準備】: REQ-002で定義された4ロールを代表する、有効化済み・既知パスワードのユーザーを生成する
        // 【初期条件設定】: is_active=true（無効化されていない）状態で用意し、ログインフローのみを検証対象とする
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        // 【実際の処理実行】: ログインフォームへ正しい認証情報を送信する
        // 【処理内容】: AuthenticatedSessionController::store() が呼び出され、ロール別リダイレクトが行われる想定
        // 【実行タイミング】: 認証成功直後、セッション再生成後にリダイレクト先が決定される
        return $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_admin_login_redirects_to_admin_specific_screen_not_generic_dashboard(): void
    {
        // 【テスト目的】: ADMINロールでのログイン後、ロール別に定義された管理ダッシュボードへリダイレクトされることを確認する
        // 【テスト内容】: ADMINユーザーでログインし、リダイレクト先URLを検証する
        // 【期待される動作】: 「/admin」配下などADMIN専用のリダイレクト先へ遷移する（汎用dashboardへの固定リダイレクトではない）
        // 🟡 信頼性レベル: TASK-0003.md 実装詳細1「admin→管理ダッシュボード」、auth-rbac-testcases.md TC-N-01に基づく
        //               （具体的なURLは後続タスクの画面実装に依存するため、Greenフェーズで定義される暫定ルートを前提とする）
        $response = $this->loginAs(UserRole::ADMIN);

        // 【結果検証】: 認証済み状態になっていること、リダイレクト先がADMIN専用パスであることを確認する
        // 【期待値確認】: ロール別リダイレクトが実装されていれば「/admin」を含むパスへ遷移するはず
        $this->assertAuthenticated(); // 【確認内容】: ログイン処理が成功し認証済み状態になっていることを確認 🔵
        $response->assertRedirect('/admin/dashboard'); // 【確認内容】: ADMINロールが管理ダッシュボード（/admin/dashboard）へリダイレクトされることを確認 🟡
    }

    public function test_sales_login_redirects_to_quotations_or_orders_list(): void
    {
        // 【テスト目的】: SALESロールでのログイン後、見積/受注一覧画面へリダイレクトされることを確認する
        // 【テスト内容】: SALESユーザーでログインし、リダイレクト先URLを検証する
        // 【期待される動作】: 「/sales」配下などSALES専用のリダイレクト先へ遷移する
        // 🟡 信頼性レベル: TASK-0003.md 実装詳細1「sales→見積/受注一覧」、auth-rbac-testcases.md TC-N-01に基づく
        $response = $this->loginAs(UserRole::SALES);

        $this->assertAuthenticated(); // 【確認内容】: ログイン処理が成功し認証済み状態になっていることを確認 🔵
        $response->assertRedirect('/sales/dashboard'); // 【確認内容】: SALESロールが見積/受注一覧（/sales/dashboard）へリダイレクトされることを確認 🟡
    }

    public function test_warehouse_login_redirects_to_shipping_instructions_list(): void
    {
        // 【テスト目的】: WAREHOUSEロールでのログイン後、出荷指示一覧画面へリダイレクトされることを確認する
        // 【テスト内容】: WAREHOUSEユーザーでログインし、リダイレクト先URLを検証する
        // 【期待される動作】: 「/warehouse」配下などWAREHOUSE専用のリダイレクト先へ遷移する
        // 🟡 信頼性レベル: TASK-0003.md 実装詳細1「warehouse→出荷指示一覧」、auth-rbac-testcases.md TC-N-01に基づく
        $response = $this->loginAs(UserRole::WAREHOUSE);

        $this->assertAuthenticated(); // 【確認内容】: ログイン処理が成功し認証済み状態になっていることを確認 🔵
        $response->assertRedirect('/warehouse/dashboard'); // 【確認内容】: WAREHOUSEロールが出荷指示一覧（/warehouse/dashboard）へリダイレクトされることを確認 🟡
    }

    public function test_accounting_login_redirects_to_invoice_management_screen(): void
    {
        // 【テスト目的】: ACCOUNTINGロールでのログイン後、請求/入金管理画面へリダイレクトされることを確認する
        // 【テスト内容】: ACCOUNTINGユーザーでログインし、リダイレクト先URLを検証する
        // 【期待される動作】: 「/accounting」配下などACCOUNTING専用のリダイレクト先へ遷移する
        // 🟡 信頼性レベル: TASK-0003.md 実装詳細1「accounting→請求/入金管理画面」、auth-rbac-testcases.md TC-N-01に基づく
        $response = $this->loginAs(UserRole::ACCOUNTING);

        $this->assertAuthenticated(); // 【確認内容】: ログイン処理が成功し認証済み状態になっていることを確認 🔵
        $response->assertRedirect('/accounting/dashboard'); // 【確認内容】: ACCOUNTINGロールが請求/入金管理画面（/accounting/dashboard）へリダイレクトされることを確認 🟡
    }

    public function test_different_roles_are_redirected_to_different_destinations(): void
    {
        // 【テスト目的】: ロールごとにリダイレクト先が分岐しており、全ロールが同一画面に固定されていないことを確認する
        // 【テスト内容】: 4ロール全員でログインし、リダイレクト先URLの集合が4種類に分かれていることを検証する
        // 【期待される動作】: 4種類のリダイレクト先URLが得られる（現状は全員dashboardに固定されており1種類しか得られない）
        // 🔵 信頼性レベル: TASK-0003.md 実装詳細1「ログイン後のリダイレクト先をUser::roleに応じて分岐させる」という要件に直接基づく
        $destinations = collect([UserRole::ADMIN, UserRole::SALES, UserRole::WAREHOUSE, UserRole::ACCOUNTING])
            ->map(function (UserRole $role) {
                $response = $this->loginAs($role);

                // 【テスト後処理】: 各ロールでのログイン確認後、次のロールの検証に影響しないようログアウトしておく
                // 【状態復元】: actingAs を使わず実ログインで検証しているため、明示的にログアウトしてセッションをクリアする
                $this->post('/logout');

                return $response->headers->get('Location');
            })
            ->unique();

        // 【結果検証】: 4ロール分のリダイレクト先URLが重複なく4種類存在することを確認する
        // 【期待値確認】: ロール別分岐が実装されていれば、リダイレクト先はロールの数だけ存在するはず
        $this->assertCount(4, $destinations); // 【確認内容】: 4ロールのリダイレクト先がすべて異なり、固定リダイレクトになっていないことを確認 🔵
    }
}
