# TASK-0009 開発コンテキストノート: 受注管理機能

## 1. 技術スタック
- Laravel 13 / PHP 8.4、Bootstrap 5 + jQuery（Blade + 部分的にJS）
- アーキテクチャパターン: Controller → Service → Repository（インターフェース + Eloquent実装）、業務データはDTO（DataTransferObjects）経由でやり取り
- DBトランザクション + 悲観的ロック（`lockForUpdate()`）による整合性制御（在庫引当解除で必須）
- Laravelポリシー（Policy）による認可制御（`OrderPolicy`新規作成）
- 参照元: CLAUDE.md, app/Services/QuotationService.php, app/Repositories/Eloquent/ProductRepository.php

## 2. 開発ルール
- レスポンスは日本語。タスク完了時は `.docs/implements/manufacture-sales-system/TASK-0009/` に要件整理・テストケース・実装記録・リファクタ記録・note.mdを作成
- タスク完了後は `.docs/tasks/manufacture-sales-system/TASK-0009.md` の完了条件チェックボックスを `- [x]` にしタイトルに完了マークを追記
- TDD実装手順: tdd-requirements → tdd-testcases → tdd-red → tdd-green → tdd-refactor → tdd-verify-complete
- 参照元: CLAUDE.md

## 3. 関連実装（参考パターン）

### モデル（既存・拡充対象）
- `app/Models/SalesOrder.php`: `status=OrderStatus` enumキャスト、`customer()`/`quotation()`/`items()`リレーション実装済み。スコープ（`scopeStatus()`等）の追加が必要
- `app/Models/SalesOrderItem.php`: `salesOrder()`/`product()`リレーション実装済み
- `app/Models/StockMovement.php`: `timestamps = false`、`reason=StockMovementReason`enumキャスト、`product()`/`relatedOrder()`/`operator()`実装済み
- `app/Models/Product.php`: `reserved_quantity`を含む在庫フィールドが定義済み
- 参照元: app/Models/SalesOrder.php, app/Models/SalesOrderItem.php, app/Models/StockMovement.php

### Enum（既存・利用可能）
- `App\Enums\OrderStatus`: CONFIRMED=1, SHIPPING_INSTRUCTED=2, SHIPPED=3, INVOICED=4, CANCELLED=5, RETURNED=6（`label()`あり）
- `App\Enums\StockMovementReason`: RESERVATION=1, RESERVATION_RELEASE=2, SHIPMENT=3, RETURN_RECEIVED=4, MANUAL_ADJUSTMENT=5
- 参照元: app/Enums/OrderStatus.php, app/Enums/StockMovementReason.php

### Repositoryパターン（既存パターンを踏襲）
- インターフェース: `app/Repositories/Contracts/{Customer,Product,Quotation}RepositoryInterface.php`
- Eloquent実装: `app/Repositories/Eloquent/{Customer,Product,Quotation}Repository.php`
- 主要メソッド: `paginate(perPage=50)`, `find(id): ?Model`, `create(Data): Model`, `update(id, Data): Model`
- `ProductRepository::adjustStock()` がトランザクション + `lockForUpdate()` + 整合性チェック + `StockMovement::create()`の参考実装。**cancel()の在庫引当解除処理はこのパターンを踏襲する**
- 参照元: app/Repositories/Eloquent/ProductRepository.php

### Serviceパターン（既存パターンを踏襲）
- コンストラクタインジェクションでRepositoryインターフェースを受け取る（`private readonly XxxRepositoryInterface $xxx`）
- `QuotationService::confirmToOrder()` が在庫引当（reserved_quantity加算）・stock_movements記録・sales_orders作成の参考実装
- 参照元: app/Services/QuotationService.php, app/Services/ProductService.php

### DTOパターン（既存パターンを踏襲）
- `app/DataTransferObjects/{Customer,Product,Quotation}Data.php`: readonlyプロパティ、`fromArray()`、`toArray()`
- TASK-0009では`UpdateSalesOrderData`等の追加が必要
- 参照元: app/DataTransferObjects/QuotationData.php

### Controller / Request（既存パターンを踏襲）
- `app/Http/Controllers/QuotationController.php`: index/create/store/show/pdf/confirm の参考実装
- `app/Http/Requests/StoreQuotationRequest.php`: authorize()=true、rules()、attributes()
- 参照元: app/Http/Controllers/QuotationController.php

### ルーティング（routes/web.php）
- `Route::middleware(['auth','verified'])`グループ内でロール制御
- 既存パターン例: /quotations 系のCRUDルートと特殊アクション（pdf, confirm）
- 参照元: routes/web.php

## 4. 設計文書・データモデル

### sales_orders テーブル（database/migrations/2026_06_07_000060_create_sales_orders_table.php）
- `id, order_number(varchar30, unique), quotation_id(FK nullable), customer_id(FK), status(tinyint, default=1), confirmed_at(nullable), cancelled_at(nullable), created_by(FK users), timestamps`
- インデックス: `idx_sales_orders_customer_id`, `idx_sales_orders_status`
- CHECK制約: `status BETWEEN 1 AND 6`
- ステータス: 1=confirmed, 2=shipping_instructed, 3=shipped, 4=invoiced, 5=cancelled, 6=returned

### sales_order_items テーブル（database/migrations/2026_06_07_000070_create_sales_order_items_table.php）
- `id, sales_order_id(FK cascadeOnDelete), product_id(FK), quantity(integer), unit_price(bigint), timestamps`
- CHECK制約（MySQLのみ）: `quantity > 0`

### stock_movements テーブル
- `reason`: 1=reservation, 2=reservation_release(引当解除), 3=shipment, 4=return_received, 5=manual_adjustment
- キャンセル時は `reason=2`（RESERVATION_RELEASE）で記録

### products テーブル（在庫フィールド）
- `stock_quantity`: 実在庫数
- `reserved_quantity`: 引当済み数量（キャンセル時に減算）
- 利用可能在庫 = `stock_quantity - reserved_quantity`

### AppServiceProvider（DIバインディング登録要）
- `SalesOrderRepositoryInterface::class → EloquentSalesOrderRepository::class` のバインド登録が必要
- `OrderPolicy` の登録も必要（`AuthServiceProvider` または `AppServiceProvider`）
- 参照元: app/Providers/AppServiceProvider.php

## 5. テスト関連情報
- PHPUnit + `RefreshDatabase` トレイト標準。3層構成: Feature（HTTPレベル統合）、Unit/Services、Unit/Repositories
- テストメソッド名: `test_xxx_can_yyy()`、Given-When-Thenコメントを付与
- アサーション: `assertRedirect`, `assertSee`, `assertDatabaseHas`, `assertDatabaseMissing`, `expectException`
- Factory（database/factories/）:
  - `SalesOrderFactory`: order_number(SO-########), quotation_id=null, customer_id, status=CONFIRMED, confirmed_at=now(), created_by（既存）
  - `SalesOrderItemFactory`: TASK-0009で新規作成が必要
  - `CustomerFactory`, `ProductFactory`: 既存
- 参照元: tests/Feature/Quotations/, tests/Unit/Services/QuotationServiceTest.php, database/factories/

## 6. 注意事項
- `OrderService::cancel()` は `DB::transaction()` 内で対象製品に `lockForUpdate()` を適用し、`reserved_quantity`減算 → `stock_movements`記録（reason=2 RESERVATION_RELEASE）→ `sales_orders.status=5`(cancelled)・`cancelled_at`更新をアトミックに実行する（`QuotationService::confirmToOrder()`の逆操作）
- キャンセル可能なステータス: `confirmed`(=1)と`shipping_instructed`(=2)のみ。`shipped`(=3)以降はキャンセル不可
- `reserved_quantity >= 0` の整合性をアプリケーションロジックで検証し、負値になる場合は例外をスローしロールバック
- `OrderPolicy::update()` でadminロールのみ受注編集を許可。`$this->authorize('update', $order)` で適用
- ステータス遷移はdataflow.mdの受注ステータス遷移図に従い、不正な遷移（例: cancelled→shipping_instructed）を防止するガード処理を実装
- TASK-0008の`QuotationService::confirmToOrder()`との責務分担: 受注作成は見積側、その後の管理（編集・キャンセル・出荷指示）は本タスクの`OrderService`
- `SalesOrderRepositoryInterface` と `EloquentSalesOrderRepository` は新規作成が必要
- `SalesOrderItemFactory` は新規作成が必要
