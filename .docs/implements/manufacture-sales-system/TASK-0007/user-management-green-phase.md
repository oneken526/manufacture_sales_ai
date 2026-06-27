# TASK-0007 実装記録: ユーザー管理機能（管理者用）

## 1. 実装方針
- TASK-0003で構築済みの認証・ロール管理基盤（`UserRole` Enum, `EnsureUserHasRole`ミドルウェア,
  `is_active`によるログイン拒否, Breeze標準パスワードリセット）を最大限再利用し、本タスクでは
  「管理者によるユーザーCRUD・有効/無効切替・パスワードリセット再送導線」のみを新規実装した。
- Customer/Productで採用されたController→Service→Repositoryパターンは、本機能が単純なCRUD（Eloquentの
  `update`/`create`をそのまま利用できる）であるため採用せず、`Admin\UserController`から`User`モデルを
  直接操作するシンプルな構成とした（過剰な抽象化を避ける方針, CLAUDE.md準拠）。

## 2. 成果物
### コントローラ・リクエスト
- `app/Http/Controllers/Admin/UserController.php`
  - `index`: ページネーション50件（NFR-021）でユーザー一覧表示
  - `create`/`store`: 新規作成フォーム表示・登録（初期`is_active=true`、パスワードはbcryptハッシュ化）
  - `edit`/`update`: 編集フォーム表示・更新（名前・メールアドレス・役割）
  - `toggleActive`: `is_active`切替。**自分自身は無効化/有効化できないようガード**（注意事項対応）
  - `sendPasswordResetLink`: `Password::sendResetLink()`によるBreeze標準のリセットメール再送
- `app/Http/Requests/StoreUserRequest.php` / `UpdateUserRequest.php`
  - `role`は`UserRole::routeKey()`の集合に対する`Rule::in`でバリデーション
  - パスワードは`Illuminate\Validation\Rules\Password::defaults()`（Breeze標準と同一基準）

### ルーティング（`routes/web.php`）
- `/admin/users`系を`['auth', 'verified', 'role:admin']`配下に追加（index/create/store/edit/update/toggle-active/send-password-reset）

### ビュー
- `resources/views/admin/users/{index,create,edit,_form}.blade.php`
  - 役割選択ドロップダウン（4ロール）+ 選択中ロールの説明テキスト（jQueryで動的表示）
  - 無効化/有効化操作は`confirm()`による確認ダイアログを経由するフォーム送信（Customer削除と同パターン）
  - フォーム送信中は「処理中...」表示でボタンを無効化（NFR-001対応）
  - バリデーションエラーは`<x-input-error>`で各項目直下に表示
- `resources/views/admin/dashboard.blade.php`にユーザー管理画面への導線リンクを追加

### テスト
- `tests/Feature/Admin/UserManagementTest.php`（7ケース、詳細は[user-management-testcases.md](user-management-testcases.md)）

## 3. テスト実行結果
```
php artisan test --filter=UserManagementTest
{"tool":"phpunit","result":"passed","tests":7,"passed":7,"assertions":30,"duration_ms":550}

php artisan test
{"tool":"phpunit","result":"passed","tests":92,"passed":90,"assertions":324,"duration_ms":3259,"skipped":2}
```
全テスト成功（既存テストの回帰なし）。

## 4. 発生した課題と対応
- **`actingAs()`とログインフローの競合**: 統合テストで「無効化されたユーザーの再ログイン拒否」を検証する際、
  直前に`actingAs($admin)`していた状態のまま`/login`へPOSTすると、`guest`ミドルウェアにより
  リダイレクトされ、`LoginRequest::authenticate()`の検証ロジックに到達せず`assertGuest()`が失敗した。
  → 無効化操作後に明示的に`/logout`を実行してから再ログインを試みる形に修正し、解消した。
- **役割バリデーション**: `role`カラムはEnum（int）だが、フォームからは`routeKey()`相当の文字列
  （`admin`/`sales`/`warehouse`/`accounting`）で受け取り、`UserRole::fromRouteKey()`でEnumへ変換する
  既存の変換方針（`EnsureUserHasRole`と同方針）に揃えた。
