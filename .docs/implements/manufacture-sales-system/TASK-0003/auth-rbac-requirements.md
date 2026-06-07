# TASK-0003: 認証・権限基盤実装 要件定義書

機能名: 認証・権限基盤（auth-rbac）
タスクID: TASK-0003
要件名: manufacture-sales-system

## 1. 機能の概要（EARS要件定義書・設計文書ベース）

- 🔵 Laravel Breezeの認証scaffoldをベースに、本システム固有の4ロール（システム管理者ADMIN・営業担当者SALES・在庫出荷担当者WAREHOUSE・管理職経理担当ACCOUNTING）に応じたアクセス制御基盤を構築する機能
- 🔵 解決する問題: ロールによって利用できる業務機能が異なる本システムにおいて、メール・パスワードによる認証、ロール別の画面誘導、権限外操作の遮断（403）を一元的な仕組み（ミドルウェア・Gate/Policy）で実現し、後続の業務機能タスク（TASK-0004〜0007）が安全に利用できる土台を提供する
- 🔵 想定ユーザー: システム管理者・営業担当者・在庫出荷担当者・管理職経理担当者の4ロール全員（ログイン・ログアウト・パスワードリセットを利用）
- 🔵 システム内での位置づけ: Laravel Breezeの認証コントローラ群、`EnsureUserHasRole`ミドルウェア、Gate/Policy（`AuthServiceProvider`/`InvoicePolicy`）から構成される、全業務機能の手前に位置するアクセス制御基盤
- **参照したEARS要件**: REQ-001, REQ-002, REQ-003, REQ-004, REQ-005, REQ-064
- **参照した設計文書**: docs/design/manufacture-sales-system/architecture.md（認証方式: Laravel Breeze セッションベース、ミドルウェア構成: auth / role middleware / Gate・Policy）

## 2. 入力・出力の仕様

- 🔵 **ログイン入力**: `email`（文字列・登録済みメールアドレス）, `password`（文字列）。`LoginRequest`（`app/Http/Requests/Auth/LoginRequest.php`）でバリデーション・認証を実施
- 🔵 **ログイン出力**: 認証成功時はセッション再生成の上、`User::role`（`UserRole` Enum: ADMIN/SALES/WAREHOUSE/ACCOUNTING）に応じたリダイレクト先URLへの`RedirectResponse`。失敗時はバリデーションエラー（メッセージ付き）で再表示
- 🔵 **`User`モデルの型**: `role`属性は`UserRole`（int backed enum, ADMIN=1/SALES=2/WAREHOUSE=3/ACCOUNTING=4）にキャスト済み（`app/Models/User.php`の`casts()`で`'role' => UserRole::class`設定済み）。`is_active`は`boolean`にキャスト済み
- 🟡 **`hasRole`ヘルパーの型**: `hasRole(UserRole $role): bool` — 引数で渡したロールと現在のユーザーのロールが一致するかを返す（本タスクで新規実装）
- 🔵 **`EnsureUserHasRole`ミドルウェアの入出力**: `handle(Request $request, Closure $next, string ...$roles): Response` — 可変長引数でロール名文字列（例: `'admin'`, `'accounting'`）を受け取り、現在の認証ユーザーの`role`が許可リストに含まれない場合は`abort(403)`、含まれる場合は`$next($request)`を返す
- 🔵 **Gate/Policyの入出力**: `Gate::define('manage-invoices', fn(User $user): bool => ...)` — `User`を受け取り`bool`を返す。`InvoicePolicy`の各アビリティ（`view`/`create`/`update`等）も`(User $user, ?Invoice $invoice = null): bool`形式
- 🔵 **データフロー**: ブラウザ → `routes/web.php`（`auth`+`role`ミドルウェア） → コントローラ → Gate/Policyによる認可チェック → ビュー/リダイレクト。権限外の場合はミドルウェアまたはPolicyの時点で403応答を返し、コントローラ処理に到達させない
- **参照したEARS要件**: REQ-001（メール・パスワードログイン）, REQ-002（4ロール）, REQ-003・REQ-064（権限制御）
- **参照した設計文書**: docs/design/manufacture-sales-system/database-schema.sql（`users.role`: `TINYINT UNSIGNED NOT NULL DEFAULT 2` CHECK 1〜4, `users.is_active`: `BOOLEAN NOT NULL DEFAULT TRUE`）, app/Enums/UserRole.php

## 3. 制約条件

- 🔵 **セキュリティ要件（NFR-010）**: パスワードはbcryptでハッシュ化して保存しなければならない。`User`モデルの`'password' => 'hashed'`キャスト（Laravel標準の`Hash::make`相当）に委ね、独自実装を行わない
- 🟡 **セキュリティ要件（NFR-011）**: セッションタイムアウトを60分に設定する。`config/session.php`の`lifetime`（`.env`の`SESSION_LIFETIME`）を`60`に変更する。現状は`120`に設定されているため修正が必要
- 🔵 **アクセス制御要件（REQ-003, REQ-064）**: 在庫出荷担当者（WAREHOUSE）は請求書関連操作に一切アクセスできず、アクセス時はHTTP 403を返す。請求書の発行・入金確認は管理職経理担当（ACCOUNTING）およびシステム管理者（ADMIN）のみ許可する
- 🔵 **アーキテクチャ制約**: ロール判定ロジックは`EnsureUserHasRole`ミドルウェアおよびGate/Policyに集約し、Controller内での重複実装を行わない（保守性確保）。権限外アクセスは403で統一し、404にすり替えない
- 🔵 **データベース制約**: `users.role`はTINYINT（1=ADMIN/2=SALES/3=WAREHOUSE/4=ACCOUNTING、CHECK制約1〜4）、`users.is_active`はBOOLEAN（デフォルトtrue）。マイグレーションはTASK-0002で適用済み（`database/migrations/2026_06_07_000010_add_role_and_is_active_to_users_table.php`）
- 🔵 **互換性要件**: Laravel 13のミドルウェアエイリアス登録方式（`bootstrap/app.php`の`withMiddleware`内で`$middleware->alias([...])`）を用いる。Laravel 13では`app/Http/Kernel.php`は使用しない
- 🟡 **実装方式の制約**: Gate定義は`AuthServiceProvider`（新規作成し`bootstrap/providers.php`へ登録）または`AppServiceProvider::boot()`内で行う。本タスクではプロジェクトに既存の`AuthServiceProvider`が無いため新規作成する方針とする
- **参照したEARS要件**: NFR-010, NFR-011, NFR-012, REQ-003, REQ-064
- **参照した設計文書**: docs/design/manufacture-sales-system/architecture.md（ミドルウェア構成、Gate/Policy方針）, docs/design/manufacture-sales-system/database-schema.sql（usersテーブル定義）

## 4. 想定される使用例

- 🔵 **基本パターン1（ログイン〜ロール別画面遷移）**: 4ロールいずれかのユーザーがメール・パスワードでログイン → 認証成功 → ロールに応じた初期画面（admin: 管理ダッシュボード, sales: 見積/受注一覧, warehouse: 出荷指示一覧, accounting: 請求/入金管理画面、いずれも後続タスクで実装される画面 or 暫定的な`dashboard`への誘導）にリダイレクト
- 🔵 **基本パターン2（権限外アクセスの拒否）**: WAREHOUSEロールのユーザーが請求書関連URLに直接アクセス → `role`ミドルウェアまたはPolicyが拒否 → HTTP 403応答、「この操作を行う権限がありません」等のメッセージ表示
- 🟡 **基本パターン3（パスワードリセット）**: ログイン画面から「パスワードを忘れた場合」を選択 → メールアドレス入力 → リセットリンクをメール送信（テスト環境では`Notification::fake()`等で検証） → 新パスワード設定 → 新パスワードでログイン可能
- 🟡 **エッジケース1（無効化アカウントでのログイン試行）**: `is_active = false`のユーザーがログインを試みる → 認証情報が正しくてもログイン拒否、「アカウントが無効化されています」等のエラーメッセージを表示し、未認証状態を維持する
- 🟡 **エッジケース2（セッションタイムアウト）**: 60分間操作がなかったユーザーが画面操作を行う → セッション切れによりログイン画面へリダイレクトされ、再ログインを促すメッセージが表示される
- 🔵 **エラーケース（認証情報不一致）**: メールアドレス・パスワードが一致しない場合、ログイン画面にバリデーションエラーメッセージを日本語で表示し、未認証状態を維持する（Laravel標準のバリデーション機構を利用）
- **参照したEARS要件**: REQ-001, REQ-002, REQ-003, REQ-004, REQ-005, REQ-064, NFR-011
- **参照した設計文書**: docs/tasks/manufacture-sales-system/TASK-0003.md（統合テスト1〜3のシナリオ）

## 5. EARS要件・設計文書との対応関係

- **参照したユーザストーリー**: 認証・ロール管理に関するストーリー（docs/spec/manufacture-sales-system/user-stories.md）
- **参照した機能要件**: REQ-001（メール・パスワードログイン）, REQ-002（4ロール定義）, REQ-003（在庫出荷担当者の請求書操作不可）, REQ-004（システム管理者によるユーザー管理・無効化）, REQ-005（パスワードリセット）, REQ-064（請求書発行・入金確認は管理職経理担当者のみ）
- **参照した非機能要件**: NFR-010（bcryptハッシュ化）, NFR-011（セッションタイムアウト60分）, NFR-012（セキュリティ要件全般）
- **参照したEdgeケース**: 無効化ユーザーのログイン拒否（REQ-004由来の派生ケース）、権限外アクセス時の403応答（REQ-003由来）
- **参照した受け入れ基準**: docs/tasks/manufacture-sales-system/TASK-0003.md の単体テスト要件1〜5、統合テスト要件1〜3
- **参照した設計文書**:
  - **アーキテクチャ**: docs/design/manufacture-sales-system/architecture.md（認証方式・ミドルウェア構成・Gate/Policy方針）
  - **データベース**: docs/design/manufacture-sales-system/database-schema.sql（`users`テーブル: role, is_activeカラム定義）
  - **Enum定義**: app/Enums/UserRole.php（UserRole: ADMIN/SALES/WAREHOUSE/ACCOUNTING）
  - **既存実装**: app/Models/User.php（castsは設定済み、hasRole等のヘルパーは未実装）, app/Http/Controllers/Auth/AuthenticatedSessionController.php（ロール別リダイレクト・is_activeチェックは未実装）, routes/web.php・bootstrap/app.php（roleミドルウェア未登録）
</content>
