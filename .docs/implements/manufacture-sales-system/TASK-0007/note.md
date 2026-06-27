# TASK-0007 開発コンテキストノート

## 1. 技術スタック
- Laravel 13 (PHP 8.4) + Eloquent ORM
- Bladeテンプレート（`x-app-layout` / Breezeコンポーネント群）+ Tailwind CSS
- jQuery 4（送信中インジケータ・役割説明テキストの動的表示に使用）
- 参照元: composer.json, resources/js/app.js, [TASK-0006のnote.md](../TASK-0006/note.md)

## 2. 開発ルール
- レスポンス・コメントは日本語（CLAUDE.md）
- ロールベースアクセス制御は`role:`ミドルウェアに委譲し、コントローラ内でロール判定を重複させない
- 単純なCRUDではService/Repository層を無理に導入せず、Controller→Eloquentの直接操作で済ませる
  （過剰な抽象化を避ける、CLAUDE.md「タスクが要求する以上の機能・リファクタ・抽象化を追加しない」方針）
- テストは`RefreshDatabase`使用、【テスト目的】【テスト内容】【期待される動作】コメント＋信頼性レベル(🔵🟡🔴)を付与

## 3. 関連実装（既存資産・本タスクで再利用したもの）
- `app/Enums/UserRole.php`: ロールEnum + `routeKey()`/`fromRouteKey()`変換ヘルパー（TASK-0003実装済み）
- `app/Http/Middleware/EnsureUserHasRole.php`: `role:admin`等のミドルウェア（TASK-0003実装済み）
- `app/Http/Requests/Auth/LoginRequest.php`: `is_active=false`ユーザーのログイン拒否ロジック（TASK-0003実装済み）
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`等: Breeze標準パスワードリセット機能（TASK-0003導入済み）
- `tests/Feature/Auth/InactiveUserLoginTest.php` / `PasswordResetTest.php`: 無効化ログイン拒否・パスワードリセットの
  単体・統合テスト（いずれもTASK-0003で実装・成功済みのため、本タスクでは流用・回帰確認の対象とした）

## 4. 設計文書からの要点
- **database-schema.sql**: `users`テーブル（`role` TINYINT, `is_active` BOOLEAN NOT NULL DEFAULT TRUE）
- **api-endpoints.md**: ユーザー管理エンドポイント群は本タスクで`admin.users.*`名前空間として新規定義
- 参照元: .docs/design/manufacture-sales-system/{database-schema.sql, api-endpoints.md}

## 5. テスト関連情報
- `phpunit.xml`: Unit/Featureの2スイート、テスト用にSQLiteインメモリDB
- `tests/Feature/Admin/UserManagementTest.php`を新規作成（7ケース、全件成功）
- 既存の`UserFactory`をそのまま利用（role/is_activeは`User::factory()->create(['role' => ..., 'is_active' => ...])`で上書き）

## 6. 注意事項・申し送り
- **`actingAs()`とログイン処理の競合に注意**: テストで「ログイン拒否の確認」を行う直前は、必ず`/logout`等で
  認証状態をクリアすること。`guest`ミドルウェアによりログイン処理自体がスキップされ、誤ったテスト結果になる
  （詳細は[user-management-green-phase.md](user-management-green-phase.md)4節参照）。
- **自分自身の無効化防止**: `UserController::toggleActive()`でガード済み。要件に明記されていなかったが
  運用上の事故防止のため実装（注意事項に基づく判断）。
- 詳細な申し送り事項は[user-management-refactor-phase.md](user-management-refactor-phase.md)を参照
