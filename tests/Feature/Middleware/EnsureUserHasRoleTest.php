<?php

namespace Tests\Feature\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TASK-0003 単体テスト要件 テストケース1 / 統合テスト要件1 に対応するテスト。
 * 対象テストケース: TC-A-01, TC-N-02, TC-B-01（auth-rbac-testcases.md）
 *
 * EnsureUserHasRole ミドルウェア（app/Http/Middleware/EnsureUserHasRole.php）はまだ存在せず、
 * bootstrap/app.php にも 'role' エイリアスが未登録のため、本テストは現時点で失敗する。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md
 */
class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 【テスト前準備】: role ミドルウェアの動作のみを検証するための専用テストルートを定義する
        // 【環境初期化】: 'role' ミドルウェアエイリアスが登録されていることを前提に、
        //                許可ロールのパターンが異なる複数ルートを用意する
        Route::middleware(['web', 'auth', 'role:admin'])
            ->get('/__test/admin-only', fn () => 'admin-only-ok')
            ->name('test.admin-only');

        Route::middleware(['web', 'auth', 'role:accounting,admin'])
            ->get('/__test/invoices', fn () => 'invoices-ok')
            ->name('test.invoices');
    }

    public function test_warehouse_role_is_forbidden_with_403_on_protected_route(): void
    {
        // 【テスト目的】: roleミドルウェアが許可ロール外のユーザーを403で拒否することを確認する
        // 【テスト内容】: role:accounting,admin が適用されたルートに WAREHOUSE ロールでアクセスする
        // 【期待される動作】: HTTP 403（Forbidden）が返却され、コントローラ処理に到達しない
        // 🔵 信頼性レベル: TASK-0003.md テストケース1・統合テスト1、auth-rbac-testcases.md TC-A-01に直接基づく

        // 【テストデータ準備】: REQ-003が想定する「在庫出荷担当者」を代表するユーザーを用意する
        // 【初期条件設定】: role=WAREHOUSE, is_active=true のユーザーを生成する
        $user = User::factory()->create(['role' => UserRole::WAREHOUSE, 'is_active' => true]);

        // 【実際の処理実行】: 認証済み状態を再現し、保護対象ルートへGETリクエストを送信する
        // 【処理内容】: actingAs()でセッション確立を擬似化し、role:accounting,admin ルートへアクセスする
        $response = $this->actingAs($user)->get('/__test/invoices');

        // 【結果検証】: レスポンスステータスが403であることを確認する
        // 【期待値確認】: 許可ロール一覧（accounting, admin）にWAREHOUSEは含まれないため abort(403) が発生するはず
        $response->assertForbidden(); // 【確認内容】: 権限外ロール（warehouse）のアクセスがHTTP 403で拒否されることを確認 🔵
    }

    public function test_accounting_role_is_allowed_to_access_protected_route(): void
    {
        // 【テスト目的】: roleミドルウェアが許可ロールに含まれるユーザーのリクエストを通過させることを確認する
        // 【テスト内容】: role:accounting,admin が適用されたルートに ACCOUNTING ロールでアクセスする
        // 【期待される動作】: 200 OK が返却され、ルート本体の処理が実行される
        // 🔵 信頼性レベル: TASK-0003.md 実装詳細2「許可ロール一覧に含まれない場合のみ拒否する」、auth-rbac-testcases.md TC-N-02に基づく

        // 【テストデータ準備】: REQ-064の許可対象ロールである「管理職・経理担当」を代表するユーザーを用意する
        // 【初期条件設定】: role=ACCOUNTING, is_active=true のユーザーを生成する
        $user = User::factory()->create(['role' => UserRole::ACCOUNTING, 'is_active' => true]);

        // 【実際の処理実行】: 認証済み状態を再現し、許可ロールに含まれるユーザーで保護対象ルートへアクセスする
        // 【処理内容】: actingAs()でセッション確立を擬似化し、role:accounting,admin ルートへアクセスする
        $response = $this->actingAs($user)->get('/__test/invoices');

        // 【結果検証】: レスポンスが正常（200 OK、ルート本体の文字列を含む）であることを確認する
        // 【期待値確認】: 許可ロールに含まれるためミドルウェアを通過し、ルート本体の処理が実行されるはず
        $response->assertOk(); // 【確認内容】: 許可ロール（accounting）でのアクセスが403にならず正常に処理されることを確認 🔵
        $response->assertSeeText('invoices-ok'); // 【確認内容】: ミドルウェアを通過しルート本体のレスポンスが返ることを確認 🔵
    }

    public function test_single_role_allow_list_distinguishes_admin_from_other_roles(): void
    {
        // 【テスト目的】: 可変長引数 ...$roles に許可ロールを1つだけ指定した場合でも正しく判定されることを確認する
        // 【テスト内容】: role:admin（単一ロール指定）のルートに ADMIN と SALES のユーザーでそれぞれアクセスする
        // 【期待される動作】: ADMINは200、SALESは403が返却される
        // 🟡 信頼性レベル: auth-rbac-testcases.md TC-B-01（可変長引数の最小要素数に関する妥当な推測）に基づく

        // 【テストデータ準備】: 許可ロール（admin）と非許可ロール（sales）の境界を確認するための代表値を用意する
        // 【初期条件設定】: role=ADMIN のユーザーと role=SALES のユーザーをそれぞれ生成する
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::SALES, 'is_active' => true]);

        // 【実際の処理実行】: 単一ロール指定ルートに対し、許可ロール・非許可ロールそれぞれでアクセスする
        // 【処理内容】: actingAs()を切り替えながら同一ルートへGETリクエストを送信する
        $adminResponse = $this->actingAs($admin)->get('/__test/admin-only');
        $salesResponse = $this->actingAs($sales)->get('/__test/admin-only');

        // 【結果検証】: 許可ロール（admin）は通過し、非許可ロール（sales）は拒否されることを確認する
        // 【期待値確認】: 許可ロールが1つだけの場合でも in_array 判定が正しく機能するはず
        $adminResponse->assertOk(); // 【確認内容】: 許可ロール（admin）が単一指定でも正しく通過することを確認 🟡
        $salesResponse->assertForbidden(); // 【確認内容】: 非許可ロール（sales）が単一指定の許可リストに対しても正しく403になることを確認 🟡
    }
}
