<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * TASK-0007 単体テスト要件・統合テスト要件に対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0007.md 単体テスト要件・統合テスト要件
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * 【テスト目的】: admin以外のロールのユーザーがユーザー管理画面にアクセスできないことを確認する
     * 【テスト内容】: sales/warehouse/accountingロールのユーザーで`/admin/users`へアクセスする
     * 【期待される動作】: HTTP 403（Forbidden）が返却される
     * 🔵 信頼性レベル: TASK-0007.md単体テスト要件 テストケース2（REQ-002, REQ-003）に直接基づく
     */
    public function test_non_admin_cannot_access_user_management_screen(): void
    {
        foreach ([UserRole::SALES, UserRole::WAREHOUSE, UserRole::ACCOUNTING] as $role) {
            $user = $this->user($role);

            $response = $this->actingAs($user)->get(route('admin.users.index'));

            $response->assertForbidden(); // 【確認内容】: admin以外のロールはユーザー管理画面へのアクセスが403で拒否されることを確認 🔵
        }
    }

    /**
     * 【テスト目的】: adminロールのユーザーがユーザー管理画面にアクセスできることを確認する
     * 【テスト内容】: adminロールのユーザーで`/admin/users`へアクセスする
     * 【期待される動作】: 一覧画面が正常に表示される
     * 🔵 信頼性レベル: TASK-0007.md完了条件「admin以外のロールのユーザーがアクセスできないこと」の裏返しとなる回帰確認
     */
    public function test_admin_can_access_user_management_screen(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk(); // 【確認内容】: adminロールではユーザー管理画面に正常にアクセスできることを確認 🔵
        $response->assertSee($admin->name); // 【確認内容】: 一覧画面にユーザー情報が表示されることを確認 🔵
    }

    /**
     * 【テスト目的】: ユーザー作成→無効化→ログイン拒否確認の一連フローがエラーなく完了することを確認する
     * 【テスト内容】: adminがユーザー管理画面から新規ユーザーを作成し、ログイン確認後、無効化し、再ログインが拒否されることを確認する
     * 【期待される動作】: 各操作が正しく完了し、無効化フラグがログイン制御に正しく反映される
     * 🟡 信頼性レベル: TASK-0007.md統合テスト1（REQ-004, REQ-005）に直接基づく
     */
    public function test_user_creation_deactivation_and_login_rejection_flow(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        // 【実際の処理実行】: admin権限のユーザーでユーザー管理画面から新規ユーザー（役割: sales）を作成する
        $storeResponse = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => '統合テスト太郎',
            'email' => 'integration-user@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'sales',
        ]);

        $createdUser = User::where('email', 'integration-user@example.com')->firstOrFail();
        $storeResponse->assertRedirect(route('admin.users.index')); // 【確認内容】: 登録後に一覧画面へリダイレクトされることを確認 🟡
        $this->assertDatabaseHas('users', [
            'email' => 'integration-user@example.com',
            'role' => UserRole::SALES,
            'is_active' => true,
        ]); // 【確認内容】: 入力内容がDBに登録され、初期状態は有効であることを確認 🟡

        // 【実際の処理実行】: 作成したユーザーでログインできることを確認する
        $this->post('/logout');
        $loginResponse = $this->post('/login', [
            'email' => $createdUser->email,
            'password' => 'password1234',
        ]);
        $loginResponse->assertRedirect(); // 【確認内容】: 作成直後のユーザーが正常にログインできることを確認 🟡
        $this->assertAuthenticatedAs($createdUser);
        $this->post('/logout');

        // 【実際の処理実行】: admin権限のユーザーが当該ユーザーを無効化する
        $toggleResponse = $this->actingAs($admin)->patch(route('admin.users.toggle-active', $createdUser));
        $toggleResponse->assertRedirect(route('admin.users.index')); // 【確認内容】: 無効化処理後に一覧画面へリダイレクトされることを確認 🟡
        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'is_active' => false,
        ]); // 【確認内容】: is_activeフラグがfalseに更新されることを確認 🟡

        // 【テスト前提整理】: ログイン試行を正しく処理させるため、actingAs状態のadminをログアウトしておく
        $this->post('/logout');

        // 【実際の処理実行】: 無効化されたユーザーが再度ログインを試み、拒否されることを確認する
        $rejectedLoginResponse = $this->post('/login', [
            'email' => $createdUser->email,
            'password' => 'password1234',
        ]);
        $this->assertGuest(); // 【確認内容】: 無効化フラグがログイン制御に正しく反映され、認証が成立しないことを確認 🟡
        $rejectedLoginResponse->assertSessionHasErrors('email');
    }

    /**
     * 【テスト目的】: 管理者が自分自身を無効化できないことを確認する（運用上の安全策）
     * 【テスト内容】: ログイン中のadminが自分自身に対して無効化操作を行う
     * 【期待される動作】: 操作が拒否され、is_activeはtrueのまま維持される
     * 🟡 信頼性レベル: TASK-0007.md注意事項「自分自身を無効化できないようにする」より
     */
    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-active', $admin));

        $response->assertRedirect(route('admin.users.index')); // 【確認内容】: 操作自体は一覧画面へリダイレクトされ、エラーが伝達されることを確認 🟡
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]); // 【確認内容】: 自分自身に対する無効化操作は反映されないことを確認 🟡
    }

    /**
     * 【テスト目的】: ユーザー作成フォームでバリデーションエラーが発生した場合に適切にエラーが返却されることを確認する
     * 【テスト内容】: 既に登録済みのメールアドレスで新規ユーザー作成を試みる
     * 【期待される動作】: バリデーションエラーが発生し、登録処理が行われない
     * 🟡 信頼性レベル: TASK-0007.md UI/UX要件「エラー表示」（メールアドレス重複等）より
     */
    public function test_user_creation_fails_with_duplicate_email(): void
    {
        $admin = $this->user(UserRole::ADMIN);
        $existing = $this->user(UserRole::SALES, ['email' => 'duplicate@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => '重複太郎',
            'email' => 'duplicate@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'sales',
        ]);

        $response->assertSessionHasErrors('email'); // 【確認内容】: メールアドレス重複時にバリデーションエラーが返却されることを確認 🟡
        $this->assertSame(1, User::where('email', 'duplicate@example.com')->count()); // 【確認内容】: 重複登録が行われないことを確認 🟡
    }

    /**
     * 【テスト目的】: adminがユーザー情報（名前・メールアドレス・役割）を編集できることを確認する
     * 【テスト内容】: 既存ユーザーの名前・メールアドレス・役割を変更する
     * 【期待される動作】: 変更内容がDBに反映される
     * 🟡 信頼性レベル: TASK-0007.md実装詳細1（edit/update: 名前・メールアドレス・役割の変更）より
     */
    public function test_admin_can_update_user_information(): void
    {
        $admin = $this->user(UserRole::ADMIN);
        $target = $this->user(UserRole::SALES);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => '更新後太郎',
            'email' => 'updated@example.com',
            'role' => 'warehouse',
        ]);

        $response->assertRedirect(route('admin.users.edit', $target)); // 【確認内容】: 更新後に編集画面へリダイレクトされることを確認 🟡
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => '更新後太郎',
            'email' => 'updated@example.com',
            'role' => UserRole::WAREHOUSE,
        ]); // 【確認内容】: 編集内容がDBに反映されることを確認 🟡
    }

    /**
     * 【テスト目的】: 管理者が任意のユーザーに対してパスワードリセットメールを送信できることを確認する
     * 【テスト内容】: adminがユーザー管理画面から特定ユーザーへのパスワードリセットメール送信を実行する
     * 【期待される動作】: Laravel Breeze標準のResetPassword通知が対象ユーザーに送信される
     * 🟡 信頼性レベル: TASK-0007.md実装詳細3（管理者が任意のユーザーに対してパスワードリセットメールを再送できる導線）より
     */
    public function test_admin_can_send_password_reset_link_to_user(): void
    {
        Notification::fake();

        $admin = $this->user(UserRole::ADMIN);
        $target = $this->user(UserRole::SALES, ['password' => Hash::make('password')]);

        $response = $this->actingAs($admin)->post(route('admin.users.send-password-reset', $target));

        $response->assertRedirect(route('admin.users.index')); // 【確認内容】: 送信後に一覧画面へリダイレクトされることを確認 🟡
        Notification::assertSentTo($target, ResetPassword::class); // 【確認内容】: Breeze標準のResetPassword通知が対象ユーザーに送信されることを確認 🟡
    }
}
