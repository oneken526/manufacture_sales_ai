# TASK-0006: 製品マスタ管理機能 - 実装記録（Green Phase）

## 実装方針
TASK-0005（顧客マスタ）のRepository+Serviceパターンを踏襲し、以下の構成で実装した。

```
Controller(ProductController)
  → Service(ProductService)         … availableQuantity / isLowStock / adjustStock 等の業務ロジックを集約
    → Repository(ProductRepositoryInterface / ProductRepository)
      → Eloquent Model(Product, StockMovement)
```

## 成果物一覧

### DTO・モデル・例外
- `app/DataTransferObjects/ProductData.php`: design/data-types.phpの定義に`isLowStock()`を追加したDTO
- `app/Models/Product.php`: `stockMovements()`リレーション（hasMany）を持つEloquentモデル
- `app/Models/StockMovement.php`: `product()`/`relatedOrder()`/`operator()`リレーションと`reason`のEnumキャストを持つモデル（タイムスタンプは`created_at`のみ）
- `app/Exceptions/StockAdjustmentViolatesIntegrityException.php`: 在庫整合性制約違反時にスローする専用例外

### Repository
- `app/Repositories/Contracts/ProductRepositoryInterface.php`
- `app/Repositories/Eloquent/ProductRepository.php`
  - `search()`: `product_code`/`product_name`に対するLIKE OR検索（クエリビルダ使用、NFR-013対応）
  - `adjustStock()`: `lockForUpdate()`で行ロックを取得した上で在庫整合性（`stock_quantity >= 0`かつ`reserved_quantity <= stock_quantity`）を検証し、`products.stock_quantity`の更新と`stock_movements`レコード作成をDBトランザクション内でアトミックに実行する

### Service
- `app/Services/ProductService.php`
  - `availableQuantity(Product $product): int` … `stock_quantity - reserved_quantity`
  - `isLowStock(Product $product): bool` … `stock_quantity < alert_threshold`
  - `adjustStock(int $productId, int $quantityChange, int $operatedBy, ?string $memo): int` … `StockMovementReason::MANUAL_ADJUSTMENT`(=5)を指定してリポジトリに委譲し、調整後在庫数を返す

### Controller・FormRequest・ルーティング
- `app/Http/Controllers/ProductController.php`: index / create / store / edit / update / adjustStockForm / adjustStock
- `app/Http/Requests/StoreProductRequest.php`, `UpdateProductRequest.php`, `AdjustStockRequest.php`
- `routes/web.php`:
  - `GET /products`（全役割）
  - `GET /products/create`, `POST /products`, `GET /products/{product}/edit`, `PUT /products/{product}`（admin）
  - `GET /products/{product}/adjust-stock`（フォーム表示）, `POST /products/{product}/adjust-stock`（warehouse, admin）
- `app/Providers/AppServiceProvider.php`: `ProductRepositoryInterface` → `ProductRepository`のバインドを追加

### ビュー
- `resources/views/products/{index,create,edit,_form,adjust-stock}.blade.php`
  - 一覧: 検索フォーム、在庫数・引当中・利用可能在庫の表示、`stock_quantity < alert_threshold`の行に「在庫不足」バッジ（赤）、`stock_quantity === 0`の行に「在庫切れ」バッジ（強調）
  - 在庫調整フォーム: 増減数・メモ入力、送信中はボタンを無効化し「処理中...」を表示（jQuery）

### ファクトリ
- `database/factories/ProductFactory.php`, `database/factories/StockMovementFactory.php`

### テスト
- `tests/Unit/Services/ProductServiceTest.php`（4ケース）
- `tests/Unit/Repositories/ProductRepositoryTest.php`（2ケース）
- `tests/Feature/Products/ProductManagementTest.php`（2ケース）

## テスト実行結果
```
php artisan test --filter=Product
{"tool":"phpunit","result":"passed","tests":11,"passed":10,"assertions":53,"skipped":1}

php artisan test
{"tool":"phpunit","result":"passed","tests":85,"passed":83,"assertions":294,"skipped":2}
```
新規追加した8テストすべてが成功し、既存テスト（TASK-0001〜0005分）への影響がないことを確認した。

## 発生した課題と対応
1. **assertSeeInOrderの誤検出**: 統合テスト2で「在庫不足製品」という製品名と警告バッジ「在庫不足」の出現順序を検証する際、
   `assertSeeInOrder(['在庫不足製品', '在庫不足'])`を使うと、`SeeInOrder`制約が次の検索開始位置を
   「直前にマッチした文字列の“開始位置”」ではなく内部的に直前マッチの終了位置からにしているにも関わらず、
   実際には製品名自体が「在庫不足」を内包するため、バッジ用の検索が製品名の先頭位置にもヒットしてしまい、
   期待した順序検証ができなかった。
   → `mb_strpos`を使い、製品名の**終了位置**から明示的に検索を開始するロジックに変更し、
     「在庫十分製品」側のセグメントにはバッジ文字列が含まれないことも合わせて検証する形にした。
2. **行ロックと整合性検証の配置**: 在庫調整の検証（`reserved_quantity <= stock_quantity`）をService層で行うと
   読み取り時点と更新時点の間で競合が発生し得るため、`ProductRepository::adjustStock()`内で`lockForUpdate()`に
   よる行ロックを取得した上で検証・更新・履歴記録を単一トランザクションで実行する設計とした
   （詳細はリファクタリング検討記録を参照）。
