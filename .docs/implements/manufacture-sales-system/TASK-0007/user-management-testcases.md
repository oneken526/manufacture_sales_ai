# TASK-0007 テストケース一覧: ユーザー管理機能（管理者用）

実装ファイル: `tests/Feature/Admin/UserManagementTest.php`（`RefreshDatabase`使用）

## 単体テスト相当

| # | テスト名 | 概要 | 結果 |
|---|---|---|---|
| 1 | test_non_admin_cannot_access_user_management_screen | sales/warehouse/accountingロールで`/admin/users`にアクセスすると403になること（テストケース2） | ✅ Pass |
| 2 | test_admin_can_access_user_management_screen | adminロールで`/admin/users`に正常にアクセスできること | ✅ Pass |
| 3 | test_admin_cannot_deactivate_themselves | 管理者が自分自身を無効化できないこと（注意事項の安全策） | ✅ Pass |
| 4 | test_user_creation_fails_with_duplicate_email | メールアドレス重複時にバリデーションエラーが表示され登録されないこと | ✅ Pass |
| 5 | test_admin_can_update_user_information | 名前・メールアドレス・役割の編集が反映されること | ✅ Pass |
| 6 | test_admin_can_send_password_reset_link_to_user | 管理者が任意ユーザーへパスワードリセットメールを送信できること（Breeze標準`ResetPassword`通知） | ✅ Pass |

※「無効化されたユーザーがログインできない」「有効なユーザーが正常にログインできる」（テストケース1・3）は
TASK-0003で`tests/Feature/Auth/InactiveUserLoginTest.php`として実装済み・成功しているため、本タスクでは
統合テスト（下記#7）内で回帰確認する形とした。

## 統合テスト

| # | テスト名 | 概要 | 結果 |
|---|---|---|---|
| 7 | test_user_creation_deactivation_and_login_rejection_flow | 統合テスト1: admin がユーザー作成→作成ユーザーでログイン確認→無効化（確認ダイアログ経由のPATCH）→再ログイン拒否、の一連フロー | ✅ Pass |

統合テスト2（パスワードリセットフロー）は TASK-0003 由来の `tests/Feature/Auth/PasswordResetTest.php` で
Breeze標準フロー（リンク発行→メール送信→新パスワード設定→再ログイン）として実装済み・成功している。
本タスクでは管理者からの再送導線（#6）を追加で検証する形とした。

## 実行結果サマリー
```
php artisan test --filter=UserManagementTest
{"tool":"phpunit","result":"passed","tests":7,"passed":7,"assertions":30}

php artisan test
{"tool":"phpunit","result":"passed","tests":92,"passed":90,"assertions":324,"skipped":2}
```
（既存テストを含む全体スイートでも regression なし。skipped 2件は本タスク範囲外の既存スキップ）
