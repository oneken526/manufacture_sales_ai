<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * TASK-0003 単体テスト要件 テストケース3 に対応するテスト。
 * 対象テストケース: TC-A-02（auth-rbac-testcases.md）
 *
 * 現状の LoginRequest::authenticate() / AuthenticatedSessionController::store() には
 * is_active のチェックが組み込まれていないため、本テストは現時点で失敗する
 * （無効化済みユーザーでも認証情報が正しければログインできてしまう）。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md
 */
class InactiveUserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login_even_with_correct_credentials(): void
    {
        // 【テスト目的】: is_active=false のユーザーが、正しい認証情報でもログインできないことを確認する
        // 【テスト内容】: 無効化済みユーザーのメールアドレス・パスワードでログインを試みる
        // 【期待される動作】: ログインが拒否され、未認証状態が維持される
        // 🟡 信頼性レベル: TASK-0003.md テストケース3、auth-rbac-testcases.md TC-A-02に基づく（REQ-004から派生する妥当な推測）

        // 【テストデータ準備】: REQ-004の無効化機能により無効化されたユーザーを想定したデータを用意する
        // 【初期条件設定】: is_active=false、認証情報自体は正しい状態（password='password'）で生成する
        $user = User::factory()->create([
            'is_active' => false,
            'password' => Hash::make('password'),
        ]);

        // 【実際の処理実行】: 正しいメールアドレス・パスワードでログインフォームを送信する
        // 【処理内容】: 認証情報の検証は通過するが、is_activeチェックでログインが拒否される想定
        // 【実行タイミング】: 認証成功直後（セッション確立前）にis_activeが検証されるべき
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // 【結果検証】: 未認証状態が維持され、無効化を伝えるエラーメッセージが表示されることを確認する
        // 【期待値確認】: is_activeチェックが実装されていれば、認証情報が正しくてもログインが成立しないはず
        $this->assertGuest(); // 【確認内容】: 認証情報が正しくてもセッションが確立されず未認証状態が維持されることを確認 🟡
        $response->assertSessionHasErrors('email'); // 【確認内容】: ログインフォームに無効化アカウント向けのバリデーションエラーが設定されることを確認 🟡
    }

    public function test_active_user_with_correct_credentials_can_still_login(): void
    {
        // 【テスト目的】: is_active=true の通常ユーザーは従来どおりログインできることを確認する（回帰確認）
        // 【テスト内容】: 有効化済みユーザーの正しい認証情報でログインする
        // 【期待される動作】: 認証に成功し、セッションが確立される
        // 🔵 信頼性レベル: 既存の tests/Feature/Auth/AuthenticationTest.php と同等の確立済みパターンに基づく

        // 【テストデータ準備】: is_active=true（有効）の通常ユーザーを用意し、無効化チェック追加による副作用がないことを確認する
        // 【初期条件設定】: is_active=true、既知パスワードで生成する
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        // 【実際の処理実行】: 正しいメールアドレス・パスワードでログインフォームを送信する
        // 【処理内容】: is_activeチェックを通過し、通常通り認証が成立する想定
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // 【結果検証】: 認証済み状態になり、ログインが成功することを確認する
        // 【期待値確認】: is_activeチェックの追加が、有効なユーザーのログインを妨げないことを保証する
        $this->assertAuthenticated(); // 【確認内容】: 有効化済みユーザーは従来どおり認証に成功することを確認（回帰防止） 🔵
        $response->assertRedirect(); // 【確認内容】: ログイン成功時にリダイレクトレスポンスが返ることを確認 🔵
    }
}
