# TASK-0003 開発ノート: 認証・権限基盤実装

## 1. 技術スタック
- PHP / Laravel 13（`composer.json`）、認証scaffoldは Laravel Breeze（Bladeスタック）
- フロントエンド: Bootstrap 5 + jQuery（`resources/views`配下のBladeテンプレート）
- DB: MySQL（本番） / SQLite `:memory:`（テスト、`phpunit.xml`）
- テスト: PHPUnit + `RefreshDatabase`トレイト、`actingAs()`によるセッション認証テスト
- 参照元: composer.json, phpunit.xml

## 2. 開発ルール
- レスポンス・コミットメッセージ等は日本語（CLAUDE.md）
- ロール判定ロジックは`EnsureUserHasRole`ミドルウェア・Gate/Policyに集約し、Controllerでの重複判定を避ける（TASK-0003.md 注意事項）
- 権限外アクセス時は必ずHTTP 403を返す（404への置き換え禁止）
- パスワードハッシュ化はLaravel標準（`Hash::make`/`'password' => 'hashed'`キャスト）に委ねる。独自実装禁止（NFR-010）
- `docs/rule`ディレクトリは存在しない（追加ルールなし）
- 参照元: CLAUDE.md, docs/tasks/manufacture-sales-system/TASK-0003.md

## 3. 関連実装（既存資産）
- **`UserRole` Enum**: `app/Enums/UserRole.php`（int backed、ADMIN=1/SALES=2/WAREHOUSE=3/ACCOUNTING=4、`label()`で日本語ラベル取得可）
- **`User`モデル**: `app/Models/User.php`
  - `casts()`に`'role' => UserRole::class`, `'is_active' => 'boolean'`が**設定済み**（TASK-0002で対応済み）
  - `hasRole()`等のヘルパーメソッドは**未実装**（本タスクで追加が必要）
- **`AuthenticatedSessionController`**: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
  - `store()`は現状 `redirect()->intended(route('dashboard', ...))` に固定リダイレクト（ロール別分岐は未実装）
  - `is_active`チェックは未実装（`LoginRequest::authenticate()`に処理を追加 or ここでチェックする想定）
- **`LoginRequest`**: `app/Http/Requests/Auth/LoginRequest.php`（Breeze標準、`authenticate()`内で資格情報検証）
- **ルーティング**: `routes/web.php`は`auth`ミドルウェアのみ使用、roleミドルウェアは未登録
- **`bootstrap/app.php`**: `withMiddleware`は空、ミドルウェアエイリアス登録は未実施。`AuthServiceProvider`は存在しない
- **`UserFactory`**: `database/factories/UserFactory.php` — `role`/`is_active`の状態メソッドは未定義（テストで`User::factory()->create(['role' => UserRole::ADMIN, ...])`のように直接指定するか、状態メソッド追加を検討）
- 参照元: app/Models/User.php, app/Enums/UserRole.php, app/Http/Controllers/Auth/AuthenticatedSessionController.php, routes/web.php, bootstrap/app.php, database/factories/UserFactory.php

## 4. 設計文書
- **要件**: `docs/spec/manufacture-sales-system/requirements.md`
  - REQ-001: メール・パスワードログイン
  - REQ-002: 4ロール（ADMIN/SALES/WAREHOUSE/ACCOUNTING）
  - REQ-003: 在庫・出荷担当者は請求書操作不可（アクセス拒否＝403）
  - REQ-004: システム管理者によるユーザー作成・編集・無効化
  - REQ-005: パスワードリセット機能
  - REQ-064: 請求書発行・入金確認は管理職・経理担当者のみ
  - NFR-010: パスワードはbcryptでハッシュ化
  - NFR-011: セッションタイムアウト60分
  - NFR-012: CSRF対策等のセキュリティ要件
- **アーキテクチャ**: `docs/design/manufacture-sales-system/architecture.md`
  - 認証方式: Laravel Breeze（セッションベース）
  - ミドルウェア構成: `auth`、ロールベースアクセス制御用の独自`role`ミドルウェア（`app/Http/Middleware/EnsureUserHasRole.php`想定）、Gate/Policyによる権限制御
- **DBスキーマ**: `docs/design/manufacture-sales-system/database-schema.sql`
  - `users`テーブル: `role TINYINT UNSIGNED NOT NULL DEFAULT 2`（CHECK制約 1〜4）, `is_active BOOLEAN NOT NULL DEFAULT TRUE`
  - 関連マイグレーション: `database/migrations/2026_06_07_000010_add_role_and_is_active_to_users_table.php`
- 参照元: docs/spec/manufacture-sales-system/requirements.md, docs/design/manufacture-sales-system/architecture.md, docs/design/manufacture-sales-system/database-schema.sql

## 5. テスト関連情報
- **テスト設定**: `phpunit.xml`（`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, `APP_ENV=testing`）
- **ディレクトリ構成**:
  - `tests/Feature/Auth/`: `AuthenticationTest.php`, `RegistrationTest.php`, `PasswordResetTest.php`, `PasswordUpdateTest.php`, `PasswordConfirmationTest.php`, `EmailVerificationTest.php`（Breeze標準テストが配置済み）
  - `tests/Feature/`: `ProfileTest.php`, `DatabaseSchemaTest.php`, `DatabaseSeederTest.php`等
  - `tests/Unit/`: `EnumsTest.php`（UserRole等のEnumテスト）
- **既存テストのパターン**（`tests/Feature/Auth/AuthenticationTest.php`）:
  - `use RefreshDatabase;` でテスト毎にDBリセット
  - `User::factory()->create()` でユーザー生成、`$this->post('/login', [...])`でログイン、`$this->actingAs($user)`で認証状態を再現
  - `$response->assertStatus(200)`, `assertRedirect()`, `$this->assertAuthenticated()`, `$this->assertGuest()`等のアサーション
- **モック等**: 特別なモック設定は無し（メール送信は`Notification::fake()`等をBreeze標準テストで利用想定、既存ファイルを参照）
- 参照元: phpunit.xml, tests/Feature/Auth/AuthenticationTest.php, tests/Feature/ProfileTest.php

## 6. 注意事項
- `SESSION_LIFETIME`は現状`.env`で`120`（分）に設定されている → NFR-011に合わせて`60`へ変更が必要（`config/session.php`の`lifetime`は`env('SESSION_LIFETIME', 120)`を参照）
- `bootstrap/app.php`の`withMiddleware`は空、`role`エイリアス登録が必要（Laravel 13方式: `$middleware->alias(['role' => EnsureUserHasRole::class])`）
- `AuthServiceProvider`は存在しない → 新規作成し`bootstrap/providers.php`への登録、または`bootstrap/app.php`/`AppServiceProvider::boot()`内で`Gate::define`する方式を検討
- `User`モデルに`hasRole(UserRole $role): bool`等のヘルパーメソッド追加が必要（現状未実装）
- ロール別リダイレクト先のルート（admin→管理ダッシュボード、sales→見積/受注一覧、warehouse→出荷指示一覧、accounting→請求/入金管理画面）は後続タスク（TASK-0005〜0007）で実装される業務画面が前提となるため、本タスクでは**仮のルート/ダッシュボードへのリダイレクト**または既存`dashboard`ルートを暫定的な分岐先として実装し、後続タスクとの整合を取る方針で進める（タスク注意事項より、リダイレクト先の実体は将来差し替え可能な設計とする）
- `is_active = false`のチェックは`LoginRequest::authenticate()`をオーバーライド、または`AuthenticatedSessionController::store()`内でログイン成功直後にチェックし、`Auth::logout()`した上でエラーメッセージを返す実装が必要
- 参照元: .env, config/session.php, app/Http/Requests/Auth/LoginRequest.php
</content>
