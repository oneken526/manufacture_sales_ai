# TASK-0008 開発コンテキストノート: 見積管理機能（作成・PDF・受注転換）

## 1. 技術スタック
- Laravel 13 / PHP 8.4、Bootstrap 5 + jQuery（Blade + 部分的にJS）、mPDF（PdfService経由）
- アーキテクチャパターン: Controller → Service → Repository（インターフェース + Eloquent実装）、業務データはDTO（DataTransferObjects）経由でやり取り
- DBトランザクション + 悲観的ロック（`lockForUpdate()`）による整合性制御（在庫調整・採番で実績あり）
- 参照元: CLAUDE.md, app/Services/ProductService.php, app/Repositories/Eloquent/ProductRepository.php

## 2. 開発ルール
- レスポンスは日本語。タスク完了時は `.docs/implements/manufacture-sales-system/TASK-0008/` に要件整理・テストケース・実装記録・リファクタ記録・note.mdを作成
- タスク完了後は `.docs/tasks/manufacture-sales-system/TASK-0008.md` の完了条件チェックボックスを `- [x]` にしタイトルに完了マークを追記
- TDD実装手順: tdd-requirements → tdd-testcases → tdd-red → tdd-green → tdd-refactor → tdd-verify-complete
- 参照元: CLAUDE.md

## 3. 関連実装（参考パターン）

### モデル
- `app/Models/Customer.php`: `#[Fillable([...])]` 属性、`HasFactory`/`SoftDeletes`、`casts()`でenum/integerキャスト、`hasMany`/`belongsTo`リレーション
- `app/Models/SalesOrder.php`: `status` を `OrderStatus` enumにキャスト。`customer()` のみ実装済み（`quotation()`リレーションは未定義 → 必要なら追加検討）
- `app/Models/StockMovement.php`: `timestamps = false`、`reason`を`StockMovementReason`enumにキャスト、`product()`/`relatedOrder()`/`operator()`の`belongsTo`
- 参照元: app/Models/Customer.php, app/Models/SalesOrder.php, app/Models/StockMovement.php

### Enum（既存・利用可能）
- `App\Enums\QuotationStatus`: DRAFT=1, CONVERTED=2, CANCELLED=3, EXPIRED=4
- `App\Enums\OrderStatus`: CONFIRMED=1, SHIPPING_INSTRUCTED=2, SHIPPED=3, INVOICED=4, CANCELLED=5, RETURNED=6（`label()`あり）
- `App\Enums\DocumentType`: QUOTATION=1, ORDER=2, INVOICE=3
- `App\Enums\StockMovementReason`: RESERVATION=1, RESERVATION_RELEASE=2, SHIPMENT=3, RETURN_RECEIVED=4, MANUAL_ADJUSTMENT=5
- 参照元: app/Enums/QuotationStatus.php, app/Enums/OrderStatus.php, app/Enums/DocumentType.php, app/Enums/StockMovementReason.php

### Repositoryパターン
- インターフェース: `app/Repositories/Contracts/{Customer,Product}RepositoryInterface.php`
- Eloquent実装: `app/Repositories/Eloquent/{Customer,Product}Repository.php`
- 主要メソッド: `paginate(perPage=50)`, `find(id): ?Model`, `create(Data): Model`, `update(id, Data): Model`, `search(keyword, perPage)`
- `ProductRepository::adjustStock()` がトランザクション + `lockForUpdate()` + 整合性チェック（不正時は`StockAdjustmentViolatesIntegrityException`）+ `StockMovement::create()`の参考実装。**confirmToOrder()の在庫引当処理はこのパターンを踏襲する**
- 参照元: app/Repositories/Eloquent/ProductRepository.php, app/Repositories/Contracts/ProductRepositoryInterface.php

### Serviceパターン
- コンストラクタインジェクションでRepositoryインターフェースを受け取る（`private readonly XxxRepositoryInterface $xxx`）
- `paginate(?keyword, perPage=50)`: キーワードがあれば`search()`、なければ`paginate()`
- 業務判定ロジック（`availableQuantity()`, `isLowStock()`等）をServiceに集約
- 参照元: app/Services/CustomerService.php, app/Services/ProductService.php

### DTOパターン
- `app/DataTransferObjects/{Customer,Product}Data.php`: readonlyプロパティ、`fromArray(array $data, ?int $id=null): self`、`toArray(): array`でDB保存用配列に変換
- 参照元: app/DataTransferObjects/CustomerData.php, app/DataTransferObjects/ProductData.php

### Controller / Request
- `app/Http/Controllers/{Customer,Product}Controller.php`: `index/create/store/show/edit/update/destroy` + 内部API用 `searchJson`
- `app/Http/Requests/Store{Customer,Product}Request.php`: `authorize()=true`（権限はミドルウェアで制御）、`rules()`、`attributes()`で日本語ラベル
- 参照元: app/Http/Controllers/CustomerController.php, app/Http/Requests/StoreCustomerRequest.php

### ルーティング（routes/web.php）
- `Route::middleware(['auth','verified'])`グループ内で `role:sales,admin` 等によりロール制御
- 内部API: `/api/internal/{resource}/search` 等を `web.php` 内に定義（`routes/api.php`は使用しない）
- 既存パターン例: L58-96付近に customers/products のCRUDルートと検索ルート
- 参照元: routes/web.php

## 4. 設計文書・データモデル

### quotations テーブル（database/migrations/2026_06_07_000040_create_quotations_table.php）
- `id, quotation_number(varchar30, unique), customer_id(FK), status(tinyint, default=1), remarks(text, nullable), expires_at(date, nullable), created_by(FK users), timestamps`
- インデックス: `idx_quotations_customer_id`

### quotation_items テーブル（database/migrations/2026_06_07_000050_create_quotation_items_table.php）
- `id, quotation_id(FK cascadeOnDelete), product_id(FK), quantity(integer), unit_price(bigint), timestamps`
- CHECK制約（MySQLのみ）: `quantity > 0`

### sales_orders テーブル（database/migrations/2026_06_07_000060_create_sales_orders_table.php）
- `id, order_number(varchar30, unique), quotation_id(FK nullable), customer_id(FK), status(tinyint, default=1), confirmed_at, cancelled_at, created_by(FK), timestamps`
- インデックス: `idx_sales_orders_customer_id`, `idx_sales_orders_status`、CHECK: `status BETWEEN 1 AND 6`

### sales_order_items テーブル（database/migrations/2026_06_07_000070_create_sales_order_items_table.php）
- `id, sales_order_id(FK cascadeOnDelete), product_id(FK), quantity(integer), unit_price(bigint), timestamps`
- CHECK制約（MySQLのみ）: `quantity > 0`

### document_sequences テーブル（database/migrations/2026_06_07_000120_create_document_sequences_table.php）
- `id, document_type(unsignedTinyInteger), fiscal_year(integer), last_number(integer, default=0), timestamps`
- UNIQUE制約: `uq_document_sequences (document_type, fiscal_year)`、CHECK: `document_type BETWEEN 1 AND 3`
- **`App\Models\DocumentSequence` モデルは未実装（新規作成が必要）**

### PdfService（TASK-0004実装済み: app/Services/PdfService.php）
- `download(view, data, filename): Response`（ダウンロード、Content-Disposition: attachment）
- `generateFromView(view, data, ?filename): string`（PDFバイナリ生成、失敗時`PdfGenerationException`）
- `buildStoragePath(type, identifier, ?year): string`（`pdf/{種別}/{年度}/{種別}_{識別子}.pdf`）
- `generateAndStore(view, data, type, identifier): string`
- PDFテンプレートは `resources/views/pdf/layouts/base.blade.php` を `@extends` して `@section('content')` を実装する構成
- 参照元: app/Services/PdfService.php, resources/views/pdf/layouts/

### AppServiceProvider（DIバインディング登録要）
- `CustomerRepositoryInterface::class → CustomerRepository::class` のように、新規`QuotationRepositoryInterface`もここにバインド登録が必要
- 参照元: app/Providers/AppServiceProvider.php

## 5. テスト関連情報
- PHPUnit + `RefreshDatabase` トレイト標準。`tests/Feature/{Customers,Products}/XxxManagementTest.php`（HTTPレベル統合テスト）、`tests/Unit/Services/XxxServiceTest.php`、`tests/Unit/Repositories/XxxRepositoryTest.php` の3層構成
- テストメソッド名: `test_xxx_can_yyy()`、Given-When-Thenコメントを付与
- アサーション: `assertRedirect`, `assertSee`, `assertDatabaseHas`, `assertDatabaseMissing`, `expectException`等
- Factory（database/factories/）:
  - `CustomerFactory`: company_name, contact_name, address, phone, email(unique), credit_limit
  - `ProductFactory`: product_code(P-######), product_name, unit_price(100-100000), unit='個', stock_quantity(0-1000), reserved_quantity=0, alert_threshold
  - `SalesOrderFactory`: order_number(SO-########), quotation_id=null, customer_id, status=CONFIRMED, confirmed_at=now(), created_by
  - `StockMovementFactory`: product_id, reason=MANUAL_ADJUSTMENT, quantity_change(-50〜50), related_order_id=null, operated_by, memo, created_at=now()
  - **`QuotationFactory`/`QuotationItemFactory` は未実装 → 新規作成が必要**
- 参照元: tests/Feature/Customers/CustomerManagementTest.php, tests/Unit/Services/ProductServiceTest.php, database/factories/

## 6. 注意事項
- `confirmToOrder()`は`DB::transaction()`内で対象製品に`lockForUpdate()`を適用し、利用可能在庫（`stock_quantity - reserved_quantity`）チェック → `reserved_quantity`加算 → `stock_movements`記録（reason=1 reservation）→ `sales_orders`/`sales_order_items`作成 → `quotations.status=2`(converted)更新、をアトミックに実行する（`ProductRepository::adjustStock()`の実装パターンを踏襲）
- 在庫不足時は`InsufficientStockException`（新規例外、`CustomerHasOrdersException`/`StockAdjustmentViolatesIntegrityException`を参考に作成）をスローしロールバック
- 採番ロジックは`document_sequences`を`lockForUpdate()`で取得し、`DocumentNumberGenerator`等の独立クラスとして実装（TASK-0009受注番号採番でも再利用想定）。`QUO-{年度}-{連番4桁}`形式
- 見積から受注への転換は本タスクで`QuotationService::confirmToOrder()`として実装し、TASK-0009の`OrderService`と責務分担する（`SalesOrder`へ`quotation()`リレーション追加が必要な可能性あり）
- `app/Models/DocumentSequence.php`、`QuotationFactory`、`QuotationItemFactory`は新規作成が必要
- `routes/web.php`へ見積関連ルート（一覧・作成・保存・詳細・PDF・受注確定 + 内部API `/api/internal/quotations/calculate`）の追加、`AppServiceProvider`への`QuotationRepositoryInterface`バインド追加が必要
