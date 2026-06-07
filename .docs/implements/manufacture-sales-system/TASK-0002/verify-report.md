# TASK-0002 設定確認・動作テスト

## 確認概要

- **タスクID**: TASK-0002
- **確認内容**: 全12テーブルのマイグレーション・CHECK制約/インデックス・Enum・シーダーの動作確認、および単体テストの実施
- **実行日時**: 2026-06-07
- **実行者**: Claude Code（DIRECTタスク自動実行）

## 設定確認結果

### 1. setup-report.md の確認

`docs/implements/manufacture-sales-system/TASK-0002/setup-report.md` を確認し、direct-setupで作成したマイグレーション12ファイル・Enum7ファイル・シーダー4ファイルが記録されていることを確認した。

### 2. マイグレーションファイルの構文・実行確認（MySQL）

```bash
php artisan migrate:fresh --seed
```

**確認結果**:
- [x] 既存3ファイル（users/cache/jobs）＋新規12ファイル＝計15マイグレーションがすべて`DONE`で完了
- [x] `UserSeeder` / `CustomerSeeder` / `ProductSeeder` がすべて`DONE`で完了

### 3. マイグレーションの実行確認（SQLite, テスト用 `:memory:`）

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --env=testing
```

**確認結果**:
- [x] SQLite接続でも全15マイグレーションが`DONE`で完了し、エラーが発生しないことを確認（DB固有構文を避けた実装になっている）

### 4. CHECK制約の作成確認（MySQL）

```sql
SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK'
```

**確認結果**: `database-schema.sql`で定義された11個のCHECK制約すべてが作成されていることを確認した。

```
chk_document_sequences_type, chk_invoices_payment_status, chk_payments_source,
chk_products_stock, chk_products_reserved, chk_products_reserved_le_stock,
chk_quotation_items_quantity, chk_sales_orders_status, chk_sales_order_items_quantity,
chk_stock_movements_reason, chk_users_role
```

### 5. CHECK制約の実効性確認（MySQL, 不正値の登録試行）

```php
DB::table('users')->insert([..., 'role' => 9, ...]);
// => SQLSTATE[23000]: Integrity constraint violation: 4025 CONSTRAINT `chk_users_role` failed

DB::table('products')->insert([..., 'stock_quantity' => 5, 'reserved_quantity' => 10, ...]);
// => SQLSTATE[23000]: Integrity constraint violation: 4025 CONSTRAINT `chk_products_reserved_le_stock` failed
```

**確認結果**: [x] 不正な値の登録が`QueryException`（DB例外）により拒否されることを確認した。

### 6. Enum・モデルキャストの確認

```php
User::where('email','admin@example.com')->first()->role      // => App\Enums\UserRole::ADMIN (enum cast)
User::where('email','admin@example.com')->first()->is_active // => bool(true)
```

**確認結果**: [x] `app/Models/User.php`の`casts()`に`role => UserRole::class`, `is_active => boolean`を追加し、Enumとして正しくキャストされることを確認した。

## コンパイル・構文チェック結果

### 1. PHP構文チェック

```bash
php -l database/migrations/2026_06_07_*.php
php -l app/Enums/*.php
php -l database/seeders/*.php
```

**チェック結果**:
- [x] 全マイグレーションファイル: 構文エラーなし（`php artisan migrate`の正常完了で実証済み）
- [x] 全Enumファイル: 構文エラーなし（PHPUnitでのオートロード・実行で実証済み）
- [x] 全シーダーファイル: 構文エラーなし（`db:seed`の正常完了で実証済み）

### 2. SQL構文チェック

CHECK制約・FK制約・ユニーク制約を含むDDLは、`migrate:fresh --seed`実行時にMySQL（本番想定）・SQLite（開発/テスト想定）の両方で正常に処理されることを確認済み（上記3・4参照）。

## 動作テスト結果（単体テスト要件 テストケース1〜5、統合テスト1）

新規に以下のテストファイルを作成し、`php artisan test`で実行した。

- `tests/Feature/DatabaseSchemaTest.php`: テストケース1（全テーブル作成確認）, テストケース2（カラム・ユニーク制約の一致確認）, テストケース3（CHECK制約による不正値拒否, MySQL限定）
- `tests/Unit/EnumsTest.php`: テストケース4（7Enumのコード値・`from()`・`label()`の検証）
- `tests/Feature/DatabaseSeederTest.php`: テストケース5（シーダーによる初期データ投入確認）, 統合テスト1（管理者roleとUserRole::ADMINの整合・在庫整合性の確認）

```bash
php artisan test --filter="DatabaseSchemaTest|EnumsTest|DatabaseSeederTest"
```

**テスト結果（SQLite, デフォルトのテスト接続）**:

```
Tests: 18, Passed: 16, Skipped: 2, Assertions: 105
```

CHECK制約のDBレベル検証2件（`test_check_constraints_reject_invalid_values_on_mysql`, `test_products_reserved_quantity_cannot_exceed_stock_quantity_on_mysql`）は、SQLiteが`ALTER TABLE ... ADD CONSTRAINT`を未サポートのため`markTestSkipped()`で明示的にスキップしている（後述の「設計判断」を参照）。

**テスト結果（MySQL接続に切り替えて再実行・検証用）**:

```
Tests: 18, Passed: 18, Skipped: 0, Assertions: 107
```

MySQL接続では上記2件もスキップされず実行され、CHECK制約違反が`QueryException`として検出されることを確認した。

**プロジェクト全体のテスト結果**:

```bash
php artisan test
# Tests: 43, Passed: 41, Skipped: 2, Assertions: 166
```

既存テスト（Auth, Profile等）を含め、全テストが成功（2件は上記理由によるスキップ）することを確認した。

## 品質チェック結果

### 設計判断: CHECK制約のSQLite/MySQL差異への対応

`database-schema.sql`で定義された11個のCHECK制約は、SQLiteが`ALTER TABLE ... ADD CONSTRAINT ... CHECK`構文をサポートしない（CHECK制約はCREATE TABLE時にのみ指定可能）ため、以下の方針で実装した（TASK-0002の「注意事項」に明記された「実装できない場合はモデルのバリデーション層やDBトリガー相当のロジックで補完する」という指針に準拠）。

- **MySQL（本番想定, 8.0.16+）**: `DB::statement()`による`ALTER TABLE ADD CONSTRAINT ... CHECK`でDBレベルの制約を適用
- **SQLite（開発・テスト用, `:memory:`）**: DBレベルのCHECK制約は適用しない。区分値は`app/Enums/`のint-backed Enumで型安全に扱われ、業務ロジックの整合性検証（在庫整合性等）は今後のモデル・サービス層実装タスクで担保する前提とする

この判断はsetup-report.md・README.mdのトラブルシューティングにも記録済み。

### セキュリティ

- [x] シーダーのパスワードは`Hash::make()`によりbcryptハッシュ化されている（NFR-010準拠）ことを`Hash::check()`で確認
- [x] User Enumキャストにより、不正な数値が`role`に混入してもアプリケーション層で型エラーとして検出できる

### パフォーマンス

- `migrate:fresh --seed`所要時間: 約1.5秒（MySQL）
- テストスイート実行時間: 約1.7秒（43テスト, SQLite `:memory:`）

## 全体的な確認結果

- [x] 設定作業が正しく完了している
- [x] 全ての動作テストが成功している（スキップ2件は設計上の理由によるものであり、MySQL環境では実行・成功することを確認済み）
- [x] 品質基準を満たしている
- [x] 次のタスク（TASK-0003: 認証・権限基盤実装）に進む準備が整っている

## 発見された問題と解決

### 問題1: SQLiteでCHECK制約をマイグレーションとして実装できない

- **問題内容**: `database-schema.sql`の11個のCHECK制約をクロスDB対応のLaravelマイグレーションとして実装しようとしたが、SQLiteは`ALTER TABLE`でのCHECK制約追加を構文上サポートしない
- **発見方法**: 設計検討時にSQLite公式ドキュメント相当の制約を確認し、実機（PDO sqlite）でも`ALTER TABLE ... ADD CONSTRAINT`が構文エラーになることを確認
- **重要度**: 中（テスト実行環境であるSQLiteでDBレベルの制約検証ができないため）
- **自動解決**: `DB::getDriverName() === 'mysql'`で分岐し、MySQLにのみ`DB::statement()`でCHECK制約を適用する実装とした。あわせて、テストでは`markTestSkipped()`によりSQLite環境でのDBレベル検証を明示的にスキップし、MySQL環境では実行・成功することを別途確認した
- **解決結果**: 解決済み（設計判断としてsetup-report.md・README.mdに記録）

### 解決実行ログ

```bash
# MySQL接続でCHECK制約・テストの実効性を確認
php artisan migrate:fresh --seed
php artisan test --filter="DatabaseSchemaTest|EnumsTest|DatabaseSeederTest"
# => Tests: 18, Passed: 18, Skipped: 0  （MySQL接続時はスキップなしで全件成功）
```

**解決結果**:
- [x] 問題1: 解決済み（設計判断として記録、MySQL環境での実効性も確認済み）

## 推奨事項

- 後続タスク（TASK-0003以降でCustomer/Product/Order等のモデルを実装する際）に、`reserved_quantity <= stock_quantity`等の業務制約をモデルバリデーションやサービス層でも検証し、SQLite環境でのDBレベルCHECK制約の不在を補完すること
- `app/Enums/`の各Enumに`label()`を追加する際は、必ず`data-types.php`の定義と同期を取ること（タスク注意事項に明記）

## 次のステップ

- TASK-0003（認証・権限基盤実装）に進む

## CLAUDE.mdへの記録内容

### 更新対象
- `./CLAUDE.md`（単一プロジェクト構成のため）

### 追加した情報
```markdown
### テスト実行
# データベーススキーマ・Enum・シーダーのテスト（TASK-0002）
php artisan test --filter="DatabaseSchemaTest|EnumsTest|DatabaseSeederTest"

### データベース操作
# 全テーブルを作り直して初期データを投入（開発環境専用、既存データは削除される）
php artisan migrate:fresh --seed
```

### 更新理由
- 既存のCLAUDE.mdにはテスト・マイグレーションの基本コマンドは記載済みだったため、本タスクで新規作成したテストファイル群の実行方法と、全テーブル再構築コマンドのみを最小限追記した
