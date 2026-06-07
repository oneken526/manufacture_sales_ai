# TASK-0003: 認証・権限基盤実装 Redフェーズ記録

機能名: 認証・権限基盤（auth-rbac）
タスクID: TASK-0003
要件名: manufacture-sales-system

## 作成したテストケース一覧

要件定義（`auth-rbac-requirements.md`）・テストケース定義（`auth-rbac-testcases.md`）に基づき、以下のテストファイル・テストメソッドを新規作成した（合計17テストメソッド／14件が失敗・エラー、3件は意図的な回帰確認テスト）。

| テストファイル | テストメソッド | 対応テストケース | 結果 |
|---|---|---|---|
| `tests/Feature/Middleware/EnsureUserHasRoleTest.php` | `test_warehouse_role_is_forbidden_with_403_on_protected_route` | TC-A-01 | ❌ Error（`Target class [role] does not exist.`） |
| 〃 | `test_accounting_role_is_allowed_to_access_protected_route` | TC-N-02 | ❌ Error（同上） |
| 〃 | `test_single_role_allow_list_distinguishes_admin_from_other_roles` | TC-B-01 | ❌ Error（同上） |
| `tests/Feature/Auth/RoleBasedLoginRedirectTest.php` | `test_admin_login_redirects_to_admin_specific_screen_not_generic_dashboard` | TC-N-01 | ❌ Failed（`/dashboard` に固定） |
| 〃 | `test_sales_login_redirects_to_quotations_or_orders_list` | TC-N-01 | ❌ Failed（同上） |
| 〃 | `test_warehouse_login_redirects_to_shipping_instructions_list` | TC-N-01 | ❌ Failed（同上） |
| 〃 | `test_accounting_login_redirects_to_invoice_management_screen` | TC-N-01 | ❌ Failed（同上） |
| 〃 | `test_different_roles_are_redirected_to_different_destinations` | TC-N-01 | ❌ Failed（リダイレクト先が1種類のみ） |
| `tests/Feature/Auth/InactiveUserLoginTest.php` | `test_inactive_user_cannot_login_even_with_correct_credentials` | TC-A-02 | ❌ Failed（`is_active=false`でも認証成立） |
| 〃 | `test_active_user_with_correct_credentials_can_still_login` | （回帰確認・新規実装の影響範囲確認） | ✅ Passed（意図的な回帰確認テスト） |
| `tests/Unit/Models/UserRoleHelperTest.php` | `test_has_role_returns_true_when_role_matches` | TC-N-04 | ❌ Error（`hasRole()`未定義） |
| 〃 | `test_has_role_returns_false_when_role_does_not_match` | TC-N-04 | ❌ Error（同上） |
| `tests/Feature/Authorization/InvoiceGateTest.php` | `test_accounting_and_admin_roles_are_allowed_to_manage_invoices`（accounting/admin） | TC-N-03 | ❌ Failed（Gate未定義のためfalse） |
| 〃 | `test_warehouse_and_sales_roles_are_not_allowed_to_manage_invoices`（warehouse/sales） | TC-N-03（拒否側の確認） | ✅ Passed（Gate未定義時のデフォルトfalseと期待値が一致するため、現時点でも成立。Gate実装後も継続して成立する想定） |
| `tests/Unit/SessionConfigurationTest.php` | `test_session_lifetime_is_configured_to_sixty_minutes` | TC-B-03 | ❌ Failed（現状120分） |

> 既存のBreeze標準テスト（`tests/Feature/Auth/AuthenticationTest.php`等）でカバー済みのTC-A-03（認証情報不一致）・TC-A-04（未認証アクセス時のリダイレクト）・TC-N-05（パスワードリセットの一連フロー）、および既に実装済みのEnumキャスト（TC-B-02）・bcryptハッシュ化（TC-B-04）は、重複テスト化を避けるため本Redフェーズでは新規作成対象から除外した。

## テスト実行結果（新規作成ファイルのみ）

```
php artisan test --filter="EnsureUserHasRoleTest|RoleBasedLoginRedirectTest|InactiveUserLoginTest|UserRoleHelperTest|InvoiceGateTest|SessionConfigurationTest"

tests: 17, passed: 3, failed: 9, errors: 5
```

### 期待される失敗内容（実装すべき機能の明確化）

1. **`Target class [role] does not exist.`**（`EnsureUserHasRoleTest`）
   → `app/Http/Middleware/EnsureUserHasRole.php` の新規作成、および `bootstrap/app.php` での `role` ミドルウェアエイリアス登録が必要
2. **`Failed asserting that two strings are equal. -'.../admin/dashboard' +'.../dashboard'`**（`RoleBasedLoginRedirectTest`）
   → `AuthenticatedSessionController::store()` のリダイレクト処理を `User::role`（`UserRole` Enum）に応じて分岐させる実装が必要。本Redフェーズでは `/admin/dashboard`, `/sales/dashboard`, `/warehouse/dashboard`, `/accounting/dashboard` という暫定パスを期待値とした（Greenフェーズで暫定ルート・ビューを用意して通過させる）
3. **`The user is authenticated / Failed asserting that true is false.`**（`InactiveUserLoginTest`）
   → `is_active = false` のユーザーのログインを拒否するロジック（`LoginRequest::authenticate()`のオーバーライド、または`AuthenticatedSessionController::store()`内でのチェック＋`Auth::logout()`＋エラーメッセージ設定）が必要
4. **`Call to undefined method App\Models\User::hasRole()`**（`UserRoleHelperTest`）
   → `app/Models/User.php` に `hasRole(UserRole $role): bool` メソッドの実装が必要
5. **`Failed asserting that false is true.`**（`InvoiceGateTest::test_accounting_and_admin_roles_are_allowed_to_manage_invoices`）
   → `Gate::define('manage-invoices', ...)` の定義（`AuthServiceProvider`の新規作成・登録、または`AppServiceProvider::boot()`での定義）が必要
6. **`Failed asserting that 120 is identical to 60.`**（`SessionConfigurationTest`）
   → `.env` の `SESSION_LIFETIME` を `120` から `60` に変更する必要がある

## 次のフェーズ（Greenフェーズ）への要求事項

- `app/Http/Middleware/EnsureUserHasRole.php` を作成し、`handle(Request $request, Closure $next, string ...$roles)` で許可ロール外を `abort(403)` する
- `bootstrap/app.php` の `withMiddleware` で `role` エイリアスを登録する（`$middleware->alias(['role' => EnsureUserHasRole::class])`）
- `app/Models/User.php` に `hasRole(UserRole $role): bool` を実装する
- `AuthenticatedSessionController::store()` を改修し、`User::role` に応じたリダイレクト先を分岐させる。本Redフェーズでは `/admin/dashboard`, `/sales/dashboard`, `/warehouse/dashboard`, `/accounting/dashboard` という暫定パス・暫定ビューをGreenフェーズで用意し、後続タスク（TASK-0005〜0007）で実画面に置き換え可能な形にする
- ログイン処理に `is_active` チェックを追加し、無効化済みユーザーを `assertGuest` 状態のまま「アカウントが無効化されています」等のメッセージで拒否する（`email`フィールドへの`assertSessionHasErrors`で検証可能な形にする）
- `AuthServiceProvider`（新規作成・`bootstrap/providers.php`へ登録）または同等の手段で `Gate::define('manage-invoices', fn (User $user) => in_array($user->role, [UserRole::ACCOUNTING, UserRole::ADMIN]))` を定義し、`InvoicePolicy`の雛形を作成する
- `.env` の `SESSION_LIFETIME` を `60` に変更する

## 補足: 意図的に成功しているテストについて

- `InactiveUserLoginTest::test_active_user_with_correct_credentials_can_still_login`：is_activeチェック追加が既存の正常ログインフローを壊さないことを保証する回帰確認テストとして、最初から成功する想定で作成した（Greenフェーズ実装後も成功し続けることを期待する）
- `InvoiceGateTest::test_warehouse_and_sales_roles_are_not_allowed_to_manage_invoices`：Laravelの`Gate::allows()`は未定義のアビリティに対してデフォルトで`false`を返すため、Gate未実装の現時点でも「拒否されるべきロールが拒否される」という期待値はたまたま成立している。Gateを正しく実装した後もこの期待値は変わらず成立し続けるため、回帰確認を兼ねた有効なテストとして残す
</content>
