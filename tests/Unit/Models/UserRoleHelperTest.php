<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0003 単体テスト要件 に対応するテスト（User::hasRole ヘルパー）。
 * 対象テストケース: TC-N-04（auth-rbac-testcases.md）
 *
 * User::hasRole(UserRole $role): bool は未実装のため、本テストは現時点で失敗する
 * （`Call to undefined method App\Models\User::hasRole()` 等のエラーが発生する想定）。
 * なお role 属性の UserRole Enum キャスト（TC-B-02相当）はTASK-0002で実装済みのため、
 * 本Redフェーズでは新規にテスト化せず対象から除外する（実装済み機能の重複テストを避けるため）。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md
 */
class UserRoleHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_returns_true_when_role_matches(): void
    {
        // 【テスト目的】: hasRole()が、ユーザーの現在のロールと一致する引数を渡した場合にtrueを返すことを確認する
        // 【テスト内容】: role=SALESのユーザーに対しhasRole(UserRole::SALES)を呼び出す
        // 【期待される動作】: 一致するロールを渡した場合はtrueが返る
        // 🟡 信頼性レベル: TASK-0003.md 実装詳細1「hasRole(UserRole $role): bool等のヘルパーメソッドを実装する」に基づくが、
        //               シグネチャ・戻り値の詳細は要件定義書からの妥当な推測

        // 【テストデータ準備】: ロール判定の対象となる代表的なユーザー（SALES）を用意する
        // 【初期条件設定】: role=SALESでユーザーを生成する
        $user = User::factory()->create(['role' => UserRole::SALES]);

        // 【実際の処理実行】: ユーザー自身のロールと一致するUserRoleを引数にhasRole()を呼び出す
        // 【処理内容】: User型安全なロール判定ヘルパーの戻り値を取得する
        $result = $user->hasRole(UserRole::SALES);

        // 【結果検証】: 戻り値がtrueであることを確認する
        // 【期待値確認】: 自分自身のロールと一致するため、判定結果はtrueになるはず
        $this->assertTrue($result); // 【確認内容】: 一致するロールを渡した場合にtrueが返ることを確認 🟡
    }

    public function test_has_role_returns_false_when_role_does_not_match(): void
    {
        // 【テスト目的】: hasRole()が、ユーザーの現在のロールと一致しない引数を渡した場合にfalseを返すことを確認する
        // 【テスト内容】: role=SALESのユーザーに対しhasRole(UserRole::ADMIN)を呼び出す
        // 【期待される動作】: 一致しないロールを渡した場合はfalseが返る
        // 🟡 信頼性レベル: TASK-0003.md 実装詳細1の要件から妥当な推測（一致・不一致の両方を判定できる必要がある）

        // 【テストデータ準備】: ロール判定の対象となる代表的なユーザー（SALES）を用意する
        // 【初期条件設定】: role=SALESでユーザーを生成する（前テストと異なるロールとの不一致を検証する）
        $user = User::factory()->create(['role' => UserRole::SALES]);

        // 【実際の処理実行】: ユーザー自身のロールと一致しないUserRoleを引数にhasRole()を呼び出す
        // 【処理内容】: 文字列比較等ではなくEnumインスタンス同士の比較で判定されることを期待する
        $result = $user->hasRole(UserRole::ADMIN);

        // 【結果検証】: 戻り値がfalseであることを確認する
        // 【期待値確認】: 自分自身のロールと一致しないため、判定結果はfalseになるはず
        $this->assertFalse($result); // 【確認内容】: 一致しないロールを渡した場合にfalseが返ることを確認 🟡
    }

}
