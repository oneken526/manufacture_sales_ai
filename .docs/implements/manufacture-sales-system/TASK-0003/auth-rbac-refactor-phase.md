# TASK-0003: 認証・権限基盤実装 Refactorフェーズ記録

機能名: 認証・権限基盤（auth-rbac）
タスクID: TASK-0003
要件名: manufacture-sales-system

## リファクタ方針

Greenフェーズで特定した課題のうち、最も優先度の高い「ロール名⇔`UserRole` Enumのマッピングが複数箇所に分散している」点を解消した。機能的な変更は行わず、既存のテスト（17件＋関連既存テスト）がすべて継続して成功することを確認しながら、小さな変更を1つずつ適用した。

## 改善内容

### 1. ロール名⇔Enumマッピングの一元化（DRY原則・保守性向上）

**Before（Greenフェーズ時点）**:
- `EnsureUserHasRole::fromName()`: ルート引数の文字列（'admin'等）→ `UserRole` への変換ロジック
- `AuthenticatedSessionController::intendedRouteFor()`: `UserRole` → ルート名（'admin.dashboard'等）への変換ロジック（`match`式）

この2箇所に同種の対応関係が分散しており、新しいロールを追加する際に両方を漏れなく修正する必要があった。

**After（リファクタ後）**: `app/Enums/UserRole.php` に変換ロジックを集約した。

```php
/**
 * 【機能概要】: ルート名やミドルウェア引数（`role:admin`等）で使用する小文字キー表現を返す
 * 【設計方針】: ロール名⇔Enumの対応関係を本Enumに一元化し、
 *              ミドルウェアやコントローラ側での重複したマッピング定義を排除する
 * 【保守性】: ロールが追加された場合もEnumのcase名と対応キーが一致するため、追加対応箇所が増えない
 * 🟡 信頼性レベル: TASK-0003.mdに直接の記載はないが、ルート名・テストコードの命名規則（admin, sales, warehouse, accounting）から妥当な推測
 */
public function routeKey(): string
{
    return strtolower($this->name);
}

/**
 * 【機能概要】: ルート名やミドルウェア引数の小文字キー表現からUserRole Enumを逆引きする
 * 【設計方針】: routeKey()と対になる変換を提供し、変換ロジックを一箇所に集約する
 * 【再利用性】: EnsureUserHasRoleミドルウェアなど、文字列引数からロールを特定する複数箇所で利用できる
 * 🟡 信頼性レベル: routeKey()と対になる妥当な推測実装
 */
public static function fromRouteKey(string $key): ?self
{
    foreach (self::cases() as $case) {
        if ($case->routeKey() === strtolower($key)) {
            return $case;
        }
    }

    return null;
}
```

`EnsureUserHasRole::handle()` は `UserRole::fromRouteKey($role)` を呼び出すだけのシンプルな実装になり、`fromName()` プライベートメソッドは削除した。

`AuthenticatedSessionController::intendedRouteFor()` も `route("{$role->routeKey()}.dashboard", absolute: false)` という1行に簡潔化した。

### 2. 不要な分岐の削除（可読性向上）

Greenフェーズの `EnsureUserHasRole::handle()` には `UserRole::tryFrom((int) $role) ?? $this->fromName($role)` という、ルート引数を数値変換してから `UserRole::tryFrom()` を試みるコードが含まれていた。実際にはミドルウェア引数は `role:admin` のように常に英字のロール名で指定される（`(int) 'admin'` は `0` となり該当する `UserRole` が存在しないため、このパスは実質到達しないデッドコードだった）。リファクタにより `UserRole::fromRouteKey($role)` への単純な変換のみとし、誤解を招く分岐を排除した。

## セキュリティレビュー

| 観点 | 確認結果 |
|---|---|
| 認可（403制御） | `EnsureUserHasRole` は `$request->user() === null` の場合も含めて確実に `abort(403)` する設計になっており、未認証・未許可ロールの両方をコントローラ到達前に遮断できている 🔵 |
| 入力値検証 | ミドルウェア引数はルート定義側の固定文字列のみを受け取り、ユーザー入力を直接処理しない。`UserRole::fromRouteKey()` は未知のキーに対して `null` を返し、`in_array` の厳密比較（第3引数 `true`）により誤って許可されることはない 🔵 |
| 認証・無効化ユーザー対応 | `is_active=false` のユーザーは `Auth::attempt()` 成功直後に `Auth::logout()` で即座に未認証へ戻し、`assertGuest()` の状態を保証している。セッション固定化攻撃等のリスクはBreeze標準の `session()->regenerate()` により対処済み 🔵 |
| 情報漏洩 | 無効化ユーザーへは「アカウントが無効化されています」という専用メッセージを返す。これは認証情報（メール・パスワード）が正しいことを示唆する点で、汎用的な「認証に失敗しました」よりも情報量が多いが、TASK-0003の要件として「無効化されたことを利用者に通知し管理者へ問い合わせを促す」UXが想定されており、要件に基づく意図的な設計判断である 🟡（要件定義書に明示はないが、無効化ユーザー向けの案内として妥当） |
| Gate定義 | `manage-invoices` Gateは `in_array($user->role, [...], true)` の厳密比較で実装されており、型の取り違えによる誤許可は発生しない 🔵 |
| CSRF/XSS/SQLi | Laravel標準の `web` ミドルウェアグループ（CSRF検証）、Bladeの自動エスケープ、Eloquent ORM（パラメータバインディング）の範囲内で実装しており、独自のリスクを追加する変更は行っていない 🔵 |

重大な脆弱性は検出されなかった。

## パフォーマンスレビュー

| 観点 | 確認結果 |
|---|---|
| `User::hasRole()` | Enum同士の `===` 比較のみで O(1)。DBアクセスや追加クエリは発生しない 🔵 |
| `EnsureUserHasRole::handle()` | 許可ロール一覧（通常1〜2件）に対する `array_map` と `in_array` のみで、リクエストごとに軽量に処理される。N+1クエリ等の懸念はない 🔵 |
| `UserRole::fromRouteKey()` | `UserRole::cases()` は4件の固定配列であり、ループ処理のコストは無視できる 🔵 |
| `manage-invoices` Gate | クロージャ内は `in_array` の比較のみで、追加のDBクエリは発生しない 🔵 |
| ロール別リダイレクト | `route()` ヘルパーによるルート名解決のみで、追加のDBアクセスは発生しない 🔵 |

重大な性能課題は検出されなかった。

## テスト実行結果

### リファクタ対象に関連するテスト（21件）

```
php artisan test --filter="EnsureUserHasRoleTest|RoleBasedLoginRedirectTest|InactiveUserLoginTest|UserRoleHelperTest|InvoiceGateTest|SessionConfigurationTest|AuthenticationTest"
tests: 21, passed: 21, assertions: 38
```

### 全体テストスイート

```
php artisan test
tests: 60, passed: 58, assertions: 196, skipped: 2
```

リファクタ前後で結果に変化なし（全件継続成功、スキップ2件はTASK-0003と無関係の既存スキップ）。2秒を超える遅いテストは検出されなかった（全体で約1.9秒）。

## 開発時生成ファイルのクリーンアップ

`debug-*`, `test-*`, `temp-*`, `*.tmp`, `*.bak` 等のパターンに該当する一時ファイルは検出されなかった（クリーンアップ対象なし）。`describe.skip`等によるテスト除外も本タスク関連のテストファイルには存在しない。

## 最終コード

最終的な実装コードの全文は以下のファイルを参照（リファクタ後の状態）:
- `app/Enums/UserRole.php`（`routeKey()`/`fromRouteKey()`追加）
- `app/Http/Middleware/EnsureUserHasRole.php`（`UserRole::fromRouteKey()`委譲によるシンプル化）
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`（`routeKey()`利用によるリダイレクト先決定の簡潔化）
- `app/Models/User.php`（`hasRole()`、変更なし）
- `app/Providers/AuthServiceProvider.php`（`manage-invoices` Gate定義、変更なし）
- `app/Policies/InvoicePolicy.php`（雛形、変更なし）
- `app/Http/Requests/Auth/LoginRequest.php`（`is_active`チェック、変更なし）

## 品質評価

✅ **高品質**

- テスト結果: 関連21テスト・全体58テストともに継続成功（リファクタ前後で差異なし）
- セキュリティ: 重大な脆弱性なし（認可・入力検証・情報漏洩の観点で確認済み、1件の意図的な設計判断を記録）
- パフォーマンス: 重大な性能課題なし（すべてO(1)〜O(4)程度の軽量処理）
- リファクタ品質: ロール名⇔Enumマッピングの一元化という目標を達成し、デッドコードも除去できた
- コード品質: 各ファイルとも100行未満で500行制限を大幅に下回り、日本語コメント（信頼性レベル付き）も充実
- モック使用: 実装コードにモック・スタブは含まれていない
