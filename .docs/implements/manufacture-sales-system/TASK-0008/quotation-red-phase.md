# TASK-0008: 見積管理機能 Redフェーズ記録

## 1. 作成したテストケース一覧

テストケース定義（`quotation-testcases.md`）の13件すべてを以下の3ファイルに実装した。

| 区分 | テストファイル | 対応するテストケース |
|---|---|---|
| Unit（Service） | `tests/Unit/Services/QuotationServiceTest.php` | TC1, TC2, TC6, TC10, TC11 |
| Unit（Repository） | `tests/Unit/Repositories/QuotationRepositoryTest.php` | TC12 |
| Feature（HTTP統合） | `tests/Feature/Quotations/QuotationManagementTest.php` | TC3, TC4, TC5, TC7, TC8, TC9, TC13 |

合計13件（目標10件以上を満たす）。テスト用Factoryとして `database/factories/QuotationFactory.php`・`database/factories/QuotationItemFactory.php` を新規作成した（既存の`CustomerFactory`/`ProductFactory`のパターンを踏襲）。

### テストメソッド一覧

1. `QuotationServiceTest::test_confirm_to_order_atomically_reserves_stock_and_creates_sales_order` — TC1（在庫引当・受注作成のアトミック処理） 🔵
2. `QuotationServiceTest::test_confirm_to_order_throws_exception_and_rolls_back_when_stock_is_insufficient` — TC6（在庫不足時の例外・ロールバック、EDGE-001） 🔵
3. `QuotationServiceTest::test_confirm_to_order_succeeds_when_available_quantity_exactly_equals_requested_quantity` — TC10（境界値: 利用可能在庫＝要求数量） 🔵
4. `QuotationServiceTest::test_confirm_to_order_throws_exception_when_available_quantity_is_one_less_than_requested` — TC11（境界値: 利用可能在庫が要求数量より1少ない） 🔵
5. `QuotationServiceTest::test_create_quotation_issues_quotation_number_in_year_and_sequence_format` — TC2（見積番号採番のフォーマット・連番） 🔵
6. `QuotationRepositoryTest::test_quotation_number_sequence_is_serialized_and_does_not_duplicate_under_repeated_calls` — TC12（採番の排他制御） 🔵
7. `QuotationManagementTest::test_sales_user_can_create_quotation_and_view_its_detail` — TC3（見積作成→詳細表示） 🔵
8. `QuotationManagementTest::test_quotation_pdf_can_be_previewed_and_downloaded` — TC4（PDFプレビュー・ダウンロード） 🔵
9. `QuotationManagementTest::test_full_flow_from_quotation_creation_to_order_confirmation_reserves_stock` — TC5（一連フロー統合テスト） 🔵
10. `QuotationManagementTest::test_order_confirmation_is_aborted_with_warning_when_stock_is_insufficient` — TC7（在庫不足時の警告表示、EDGE-001統合テスト） 🔵
11. `QuotationManagementTest::test_quotation_with_no_items_fails_validation_on_store` — TC8（明細0件のバリデーションエラー） 🟡
12. `QuotationManagementTest::test_expired_quotation_cannot_be_confirmed_to_order` — TC9（期限切れ見積の受注確定拒否、REQ-033） 🟡
13. `QuotationManagementTest::test_confirm_button_is_disabled_when_quotation_has_no_items` — TC13（境界値: 明細0件時のボタン非活性化、EDGE-011） 🟡

## 2. テストコード

実装したテストコードは以下の3ファイルを参照:
- `tests/Unit/Services/QuotationServiceTest.php`
- `tests/Unit/Repositories/QuotationRepositoryTest.php`
- `tests/Feature/Quotations/QuotationManagementTest.php`

テスト用Factory:
- `database/factories/QuotationFactory.php`
- `database/factories/QuotationItemFactory.php`

## 3. テスト実行結果（失敗の確認）

```
php artisan test tests/Unit/Services/QuotationServiceTest.php tests/Unit/Repositories/QuotationRepositoryTest.php tests/Feature/Quotations/QuotationManagementTest.php
```

実行結果: `tests: 13, passed: 0, errors: 13`（全件失敗）

主な失敗理由（クラス・ルート未定義によるFatal Error）:

| エラー内容 | 該当クラス・ルート | 原因 |
|---|---|---|
| `Class "App\Models\Quotation" not found` | `App\Models\Quotation` | モデル未実装 |
| `Target class [App\Services\QuotationService] does not exist.` | `App\Services\QuotationService` | サービス未実装 |
| `Target class [App\Repositories\Contracts\QuotationRepositoryInterface] does not exist.` | `App\Repositories\Contracts\QuotationRepositoryInterface` | リポジトリインターフェース未実装・DI未バインド |
| `Route [quotations.store] not defined.` | `routes/web.php` の見積関連ルート | ルーティング・コントローラ未実装 |

これは「まだ実装されていない機能をテストする」というRedフェーズの原則に合致する、想定通りの失敗である。

## 4. Greenフェーズで実装すべき内容

テストを通すために、以下を実装する必要がある（TASK-0008.md実装詳細1〜6と対応）:

1. **モデル**: `app/Models/Quotation.php`（`customer`, `items`, `salesOrder`, `createdBy`リレーション）, `app/Models/QuotationItem.php`（`quotation`, `product`リレーション）, `app/Models/DocumentSequence.php`
2. **DTO**: `app/DataTransferObjects/QuotationData.php`, `app/DataTransferObjects/QuotationItemData.php`（テストでコンストラクタ引数 `id, customerId, items, remarks, expiresAt, createdBy` / `productId, quantity, unitPrice` を使用）
3. **例外**: `app/Exceptions/InsufficientStockException.php`（`productId`, `requestedQuantity`, `availableQuantity` プロパティを公開）
4. **Repository**: `app/Repositories/Contracts/QuotationRepositoryInterface.php`・`app/Repositories/Eloquent/QuotationRepository.php`（`issueQuotationNumber(int $year): string` を含む）。`AppServiceProvider`へのバインド登録
5. **Service**: `app/Services/QuotationService.php`（`create(QuotationData $data): Quotation`, `confirmToOrder(Quotation $quotation): void`）
   - `create()`: `document_sequences`を`lockForUpdate()`で取得・更新し`QUO-{年度}-{連番4桁}`形式の見積番号を発行してから`Quotation`/`QuotationItem`を保存する
   - `confirmToOrder()`: `DB::transaction()`内で対象製品を`lockForUpdate()`し、利用可能在庫チェック→不足なら`InsufficientStockException`、十分なら`reserved_quantity`加算・`stock_movements`記録(reason=1)・`sales_orders`/`sales_order_items`作成・`quotations.status=2`更新を行う。期限切れ見積は確定不可とする
6. **Controller・Request・Routing**: `app/Http/Controllers/QuotationController.php`（`index/create/store/show/pdf/confirm`）、`app/Http/Requests/StoreQuotationRequest.php`（明細1件以上必須のバリデーション）、`routes/web.php`への`quotations.*`ルート追加（`role:sales,admin`ミドルウェア）
7. **PDFテンプレート**: `resources/views/pdf/quotations/show.blade.php`（PdfServiceで`application/pdf`レスポンスを生成）
8. **Blade画面**: `resources/views/quotations/{index,create,show}.blade.php`（明細一覧・合計金額表示、受注確定ボタンの`disabled`制御と理由表示、成功・警告メッセージ表示、有効期限切れメッセージ表示）
9. **`SalesOrder`モデルへの`quotation()`リレーション追加**（テストで`$quotation->salesOrder`または`SalesOrder::where('quotation_id', ...)`を使用）

これらはすべてGreenフェーズで「テストを通すための最小実装」として順次実装する。
