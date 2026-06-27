# TASK-0006 開発コンテキストノート

## 1. 技術スタック
- Laravel 13 (PHP 8.4) + Eloquent ORM
- Bladeテンプレート（`x-app-layout` / Breezeコンポーネント群）+ Tailwind CSS
- jQuery 4（在庫調整フォームの送信中インジケータに使用）
- 参照元: composer.json, resources/js/app.js, [TASK-0005のnote.md](../TASK-0005/note.md)

## 2. 開発ルール
- レスポンス・コメントは日本語（CLAUDE.md）
- レイヤードアーキテクチャ: Controller → Service → Repository → Eloquent Model → DB（architecture.md）
- ロールベースアクセス制御は`role:`ミドルウェアに委譲し、コントローラ内でロール判定を重複させない
- DTOは`App\DataTransferObjects`配下、Repositoryは`Contracts`/`Eloquent`に分離し、
  `AppServiceProvider::register()`でインターフェースをバインドする（TASK-0005と同方針）
- テストは`RefreshDatabase`使用、【テスト目的】【テスト内容】【期待される動作】コメント＋信頼性レベル(🔵🟡🔴)を付与

## 3. 関連実装（既存資産）
- `database/migrations/..._create_products_table.php`: productsテーブル定義済み（chk_products_stock等のCHECK制約はMySQLのみ適用）
- `database/migrations/..._create_stock_movements_table.php`: stock_movementsテーブル定義済み（chk_stock_movements_reason制約）
- `app/Enums/StockMovementReason.php`: 在庫変動理由Enum（1=引当, 2=引当解除, 3=出荷減算, 4=返品加算, 5=手動調整）定義済み
- `app/Enums/UserRole.php`: ロールEnum（admin/sales/warehouse/accounting）
- `app/Models/Customer.php`・`app/Repositories/{Contracts,Eloquent}/CustomerRepository*.php`・`app/Services/CustomerService.php`:
  TASK-0005で実装済みのRepository+Serviceパターンの参考実装
- 参照元: 上記各ファイル

## 4. 設計文書からの要点
- **data-types.php**: `ProductData`DTOの定義（productCode, productName, unitPrice, unit, stockQuantity, reservedQuantity, alertThreshold, availableQuantity()）
- **database-schema.sql**: `products`テーブル（chk_products_stock, chk_products_reserved, chk_products_reserved_le_stock）、
  `stock_movements`テーブル（reason, quantity_change, related_order_id, operated_by, memo, created_at）
- **api-endpoints.md**: 製品管理エンドポイント群（GET/POST /products, PUT /products/{product}, POST /products/{product}/adjust-stock）
- 参照元: .docs/design/manufacture-sales-system/{data-types.php, database-schema.sql, api-endpoints.md}

## 5. テスト関連情報
- `phpunit.xml`: Unit/Featureの2スイート、テスト用にSQLiteインメモリDB（CHECK制約はMySQL限定のため、
  整合性検証はアプリケーション層のテスト（StockAdjustmentViolatesIntegrityException）で担保する）
- 製品・在庫変動のFactoryが存在しなかったため、本タスクで`ProductFactory`/`StockMovementFactory`を新規作成した

## 6. 注意事項・申し送り
- **CHECK制約はSQLiteで効かない**: テスト環境（SQLiteインメモリ）では`chk_products_reserved_le_stock`等のDB制約が
  適用されないため、整合性検証はアプリケーション層（`ProductRepository::adjustStock()`内の`lockForUpdate`+検証）で
  確実に行う必要がある。本タスクではこの検証ロジックを単体テストで直接確認している
- **`/inventory/{product}/movements`はTASK-0011の責務**: TASK-0006の統合テスト1のシナリオ説明には
  在庫変動履歴一覧画面への言及があるが、`InventoryController`・該当ルートはTASK-0011で実装される設計のため、
  本タスクの統合テストでは`stock_movements`テーブルへのレコード作成をDBアサーションで直接確認する形とした
- 詳細な申し送り事項は[product-master-refactor-phase.md](product-master-refactor-phase.md)を参照
