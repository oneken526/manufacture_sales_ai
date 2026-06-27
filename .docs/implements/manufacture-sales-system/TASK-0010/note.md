# TASK-0010 開発コンテキストノート

## 1. 技術スタック
- Laravel 13 (PHP 8.4) + Eloquent ORM
- Bladeテンプレート（`x-app-layout` / Breezeコンポーネント群）+ Tailwind CSS
- jQuery 4 + Bootstrap 5（`resources/js/app.js`でグローバル公開済み）
- mPDF（PDFService経由）: TASK-0004で実装済み `app/Services/PdfService.php`
- 参照元: composer.json, package.json, app/Services/PdfService.php

## 2. 開発ルール
- レスポンス・コメントは日本語（CLAUDE.md）
- レイヤードアーキテクチャ: Controller → Service → Repository → Eloquent Model → DB（architecture.md）
- ロールベースアクセス制御は`EnsureUserHasRole`ミドルウェア（`role:warehouse,admin`等）に委譲
- DTOは`App\DataTransferObjects`配下、Repositoryは`Contracts`/`Eloquent`に分離し、
  `AppServiceProvider::register()`でインターフェースをバインドする
- テストは`RefreshDatabase`使用、PHPUnit属性ベース、
  【テスト目的】【テスト内容】【期待される動作】コメント＋信頼性レベル(🔵🟡🔴)を付与
- 在庫操作は必ず`DB::transaction()`内 + `lockForUpdate()`で行ロック

## 3. 関連実装（既存資産）
- `app/Enums/OrderStatus.php`: CONFIRMED=1, SHIPPING_INSTRUCTED=2, SHIPPED=3, INVOICED=4, CANCELLED=5, RETURNED=6
- `app/Enums/StockMovementReason.php`: 在庫変動理由のEnum（SHIPMENT=3, RETURN_RECEIVED=4）
- `app/Services/OrderService.php`: cancel()でトランザクション+lockForUpdate()の参考実装あり
- `app/Services/PdfService.php`: PDF生成サービス（TASK-0004実装済み）
- `app/Models/SalesOrder.php`: items()リレーション等定義済み
- `app/Models/StockMovement.php`: 在庫変動履歴モデル
- `database/migrations/2026_06_07_000080_create_shipments_table.php`: shipmentsテーブル定義済み
  - id, sales_order_id, shipped_at, delivery_note_path, returned_at, return_reason, shipped_by

## 4. 設計文書からの要点
- **dataflow.md 機能2**: ShipmentServiceのcompleteShipment()でトランザクション内で
  stock_quantity・reserved_quantityを同時減算し、stock_movements記録→ステータスshipped更新
  →PDFはトランザクション外で生成（PDF失敗でも在庫減算はロールバックしない設計）
- **api-endpoints.md**: GET /shipments, POST /shipments/{order}/complete,
  GET /shipments/{shipment}/delivery-note, POST /shipments/{shipment}/return
- **architecture.md**: `app/Services/ShipmentService.php`はディレクトリ構成に明記済み
- 参照元: .docs/design/manufacture-sales-system/

## 5. テスト関連情報
- `phpunit.xml`: Unit/Featureの2スイート、テスト用にSQLiteインメモリDB
- `database/factories/`: SalesOrderFactory, ProductFactory等の既存Factoryを活用
- OrderService::cancel()のテストが参考実装（DB::transaction + lockForUpdate）

## 6. 注意事項・申し送り
- **PDF生成はトランザクション外**: 在庫減算・ステータス更新はコミット後にPDF生成する設計
  （PDF失敗時は在庫整合性を優先してロールバックしない）
- **warehouse権限は請求書操作不可**: REQ-003により、warehouseロールには請求関連エンドポイントへのアクセスを禁止
- **返品は全量返品を基本**: 部分返品の要否は要件に明記なし（本タスクでは全量返品で実装）
- **delivery_note_path**: storage/app/delivery_notes/に保存し相対パスをDBに記録
