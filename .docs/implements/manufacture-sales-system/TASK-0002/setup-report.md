# TASK-0002 設定作業実行

## 作業概要

- **タスクID**: TASK-0002
- **作業内容**: データベース設計に基づく全12テーブルのマイグレーション作成、CHECK制約・インデックスの実装、Enum実装、初期データ投入用シーダー作成
- **実行日時**: 2026-06-07
- **実行者**: Claude Code（DIRECTタスク自動実行）

## 設計文書参照

- **参照文書**: `docs/design/manufacture-sales-system/database-schema.sql`, `docs/design/manufacture-sales-system/data-types.php`, `docs/tasks/manufacture-sales-system/TASK-0002.md`
- **関連要件**: REQ-002, REQ-010, REQ-020, REQ-030, REQ-040, REQ-050, REQ-060, REQ-070

## 実行した作業

### 1. マイグレーションファイルの作成（全12テーブル相当）

`database/migrations/` 配下に以下のファイルを作成した（実行順を考慮し `2026_06_07_0000XX` のタイムスタンプを付与）。

- `2026_06_07_000010_add_role_and_is_active_to_users_table.php` — 既存 `users` テーブルに `role`（TINYINT UNSIGNED, デフォルト2）・`is_active`（BOOLEAN, デフォルトTRUE）を追加
- `2026_06_07_000020_create_customers_table.php` — `customers`（ソフトデリート対応、`idx_customers_company_name`）
- `2026_06_07_000030_create_products_table.php` — `products`（`idx_products_product_code`, `idx_products_product_name`）
- `2026_06_07_000040_create_quotations_table.php` — `quotations`（`customer_id`/`created_by` FK、`idx_quotations_customer_id`）
- `2026_06_07_000050_create_quotation_items_table.php` — `quotation_items`（`quotation_id` CASCADE、`product_id` FK）
- `2026_06_07_000060_create_sales_orders_table.php` — `sales_orders`（`quotation_id`/`customer_id`/`created_by` FK、`idx_sales_orders_customer_id`, `idx_sales_orders_status`）
- `2026_06_07_000070_create_sales_order_items_table.php` — `sales_order_items`（`sales_order_id` CASCADE、`product_id` FK）
- `2026_06_07_000080_create_shipments_table.php` — `shipments`（`sales_order_id`/`shipped_by` FK、`idx_shipments_sales_order_id`）
- `2026_06_07_000090_create_invoices_table.php` — `invoices`（`sales_order_id` UNIQUE FK、`issued_by` FK、`idx_invoices_payment_status`）
- `2026_06_07_000100_create_payments_table.php` — `payments`（`invoice_id` FK、`idx_payments_invoice_id`）
- `2026_06_07_000110_create_stock_movements_table.php` — `stock_movements`（`product_id`/`related_order_id`/`operated_by` FK、`idx_stock_movements_product_id`, `idx_stock_movements_created_at`）
- `2026_06_07_000120_create_document_sequences_table.php` — `document_sequences`（`uq_document_sequences` 複合ユニーク制約）

外部キーは `database-schema.sql` の依存関係（`quotation_items`は`quotations`と`products`の両方に依存等）を考慮し、参照先テーブルが先に作成されるようタイムスタンプ順を設定した。

### 2. CHECK制約の実装（SQLite/MySQL両対応）

SQLiteは `ALTER TABLE ... ADD CONSTRAINT` によるCHECK制約の事後追加をサポートしないため（CREATE TABLE時のみ指定可能）、各マイグレーションで以下のように実装した。

```php
if (DB::getDriverName() === 'mysql') {
    DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock CHECK (stock_quantity >= 0)');
    // ...
}
```

- MySQL（8.0.16+、本番想定）: `DB::statement()` による `ALTER TABLE ADD CONSTRAINT ... CHECK` でDBレベルの制約を適用
- SQLite（開発・テスト用）: CHECK制約の事後追加が構文上不可能なため、マイグレーションレベルでは適用しない。アプリケーション層（Enumのint-backed制約、今後のモデルバリデーション）で同等の制約を担保する二重防御方針とする（タスク注意事項に基づく対応）

実装したCHECK制約: `chk_users_role`, `chk_products_stock`, `chk_products_reserved`, `chk_products_reserved_le_stock`, `chk_quotation_items_quantity`, `chk_sales_order_items_quantity`, `chk_sales_orders_status`, `chk_invoices_payment_status`, `chk_payments_source`, `chk_stock_movements_reason`, `chk_document_sequences_type`

ユニーク制約 `uq_document_sequences`（`document_type`, `fiscal_year`）はクロスDBで利用可能な `$table->unique()` で実装した。

### 3. Enum実装

`app/Enums/` 配下に `data-types.php` の定義をそのまま転記する形で7つのint-backed Enumを作成した。

- `UserRole`（ADMIN=1/SALES=2/WAREHOUSE=3/ACCOUNTING=4、`label()`あり）
- `QuotationStatus`（DRAFT=1/CONVERTED=2/CANCELLED=3/EXPIRED=4、`label()`なし＝設計文書に定義なし）
- `OrderStatus`（CONFIRMED=1〜RETURNED=6、`label()`あり）
- `PaymentStatus`（UNPAID=1/PARTIALLY_PAID=2/PAID=3、`label()`なし）
- `PaymentSource`（MANUAL=1/CSV_IMPORT=2、`label()`なし）
- `StockMovementReason`（RESERVATION=1〜MANUAL_ADJUSTMENT=5、`label()`なし）
- `DocumentType`（QUOTATION=1/ORDER=2/INVOICE=3、`label()`なし）

`label()` の有無は `data-types.php` の定義に完全準拠（UserRoleとOrderStatusのみ定義されているため、他のEnumには追加していない）。

また `app/Models/User.php` を更新し、`role`/`is_active` を `Fillable` に追加、`role` を `UserRole` Enumに、`is_active` を `boolean` にキャストするよう設定した。

### 4. シーダーの作成

- `database/seeders/UserSeeder.php` — 初期管理者ユーザー（role=ADMIN、`Hash::make()`によるbcryptハッシュ化、NFR-010準拠）と営業/在庫出荷/経理の各役割テストユーザーを作成
- `database/seeders/CustomerSeeder.php` — サンプル顧客3件（会社名・担当者名・住所・電話・メール・与信枠を含む）
- `database/seeders/ProductSeeder.php` — サンプル製品3件（品番・製品名・単価・在庫数・引当数・単位を含み、`reserved_quantity <= stock_quantity` を満たす値で構成）
- `database/seeders/DatabaseSeeder.php` — 上記3シーダーを呼び出すよう更新（開発・テスト用途であることを各シーダーのクラスコメントに明記）

## 作業結果

- [x] 全12テーブル分のマイグレーション作成完了
- [x] 外部キー制約・CHECK制約・インデックスの実装完了（CHECK制約はMySQLのみ、SQLiteは構文上の制約により適用対象外）
- [x] シーダー作成完了
- [x] app/Enums配下に7つのEnum実装完了
- [x] `php artisan migrate:fresh --seed`（MySQL接続）が正常終了することを確認
- [x] `php artisan migrate:fresh`（SQLite接続、テスト用 `:memory:`）が正常終了することを確認
- [x] CHECK制約（`chk_users_role`, `chk_products_reserved_le_stock`等）が不正値の登録を拒否することを確認

## 動作確認結果

```
$ php artisan migrate:fresh --seed
... 全15マイグレーション DONE
... UserSeeder / CustomerSeeder / ProductSeeder DONE

$ DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --env=testing
... 全15マイグレーション DONE（SQLiteでもエラーなし）

# CHECK制約の検証（MySQL）
INSERT INTO users (..., role=9, ...)                          => REJECTED: chk_users_role failed
INSERT INTO products (..., stock_quantity=5, reserved_quantity=10, ...)
                                                               => REJECTED: chk_products_reserved_le_stock failed

# Enum・キャストの検証
User::role                                                     => App\Enums\UserRole::ADMIN (enum cast)
User::is_active                                                => bool(true)
```

## 遭遇した問題と解決方法

### 問題1: SQLiteでCHECK制約をマイグレーションとして実装できない

- **発生状況**: `database-schema.sql` で定義された11個のCHECK制約をLaravelマイグレーションとしてSQLite/MySQL両対応で実装しようとした
- **エラーメッセージ**: SQLiteは `ALTER TABLE ... ADD CONSTRAINT` 構文をサポートしておらず、CHECK制約はテーブル作成時にしか指定できない（Laravel Schema Builderにも `check()` 相当のメソッドは存在しない）
- **解決方法**: タスクの注意事項「実装できない場合はモデルのバリデーション層やDBトリガー相当のロジックで補完する」に従い、本番想定であるMySQL（8.0.16+）には `DB::statement()` でのALTER TABLE ADD CONSTRAINTを適用し、SQLite（開発・テストDB）ではDBレベルのCHECK制約を適用しない方針とした。アプリケーション層での同等の検証は、本タスクではモデル未実装のため後続タスク（バリデーション・サービス層実装）での担保を前提とする

## 次のステップ

- `/tsumiki:direct-verify` を実行して設定を確認
- 必要に応じて単体テスト（マイグレーション実行確認・Enum値検証）を作成
