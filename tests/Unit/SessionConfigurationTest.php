<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * TASK-0003 単体テスト要件 テストケース5 に対応するテスト。
 * 対象テストケース: TC-B-03（auth-rbac-testcases.md）
 *
 * 現状 .env の SESSION_LIFETIME は 120（分）に設定されており、NFR-011が定める60分と
 * 一致しないため、本テストは現時点で失敗する。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md
 */
class SessionConfigurationTest extends TestCase
{
    public function test_session_lifetime_is_configured_to_sixty_minutes(): void
    {
        // 【テスト目的】: NFR-011が定めるセッションタイムアウト（60分）が設定ファイルに反映されていることを確認する
        // 【テスト内容】: config('session.lifetime') を取得し、値が60であることを検証する
        // 【期待される動作】: config/session.php の lifetime（.env の SESSION_LIFETIME）が60に設定されている
        // 🟡 信頼性レベル: TASK-0003.md テストケース5、auth-rbac-testcases.md TC-B-03に基づく（NFR-011自体がrequirements.mdで🟡と位置づけ）

        // 【テストデータ準備】: アプリケーションのコンフィグがロード済みである前提で、設定値を直接取得する
        // 【初期条件設定】: テスト環境（phpunit.xml）でも config/session.php → .env の値が反映される
        $lifetime = config('session.lifetime');

        // 【実際の処理実行】: config()ヘルパーでセッションの有効期限（分単位）を取得する
        // 【処理内容】: .env の SESSION_LIFETIME=60 への変更が config/session.php を通じて反映されているかを確認する

        // 【結果検証】: 取得した値が境界値である60と完全に一致することを確認する
        // 【期待値確認】: 現状値（120）でも近似値（61等）でもなく、要件が定める唯一の正解値「60」であるべき
        $this->assertSame(60, $lifetime); // 【確認内容】: セッションタイムアウトが境界値60分に設定されていることを確認（現状120のため失敗するはず） 🟡
    }
}
