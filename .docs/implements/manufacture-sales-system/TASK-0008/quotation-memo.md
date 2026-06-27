# TDD開発メモ: 見積管理機能（作成・PDF・受注転換）

## 概要

- 機能名: 見積管理機能（作成・PDF・受注転換）
- 開発開始: 2026-06-08
- 現在のフェーズ: Refactor（完了） → 完了検証（次フェーズ）

## 関連ファイル

- 元タスクファイル: `.docs/tasks/manufacture-sales-system/TASK-0008.md`
- 要件定義: `.docs/implements/manufacture-sales-system/TASK-0008/quotation-requirements.md`
- テストケース定義: `.docs/implements/manufacture-sales-system/TASK-0008/quotation-testcases.md`
- Redフェーズ記録: `.docs/implements/manufacture-sales-system/TASK-0008/quotation-red-phase.md`
- 実装ファイル（Greenフェーズで作成予定）:
  - `app/Models/Quotation.php`, `app/Models/QuotationItem.php`, `app/Models/DocumentSequence.php`
  - `app/DataTransferObjects/QuotationData.php`, `app/DataTransferObjects/QuotationItemData.php`
  - `app/Exceptions/InsufficientStockException.php`
  - `app/Repositories/Contracts/QuotationRepositoryInterface.php`, `app/Repositories/Eloquent/QuotationRepository.php`
  - `app/Services/QuotationService.php`
  - `app/Http/Controllers/QuotationController.php`, `app/Http/Requests/StoreQuotationRequest.php`
  - `resources/views/pdf/quotations/show.blade.php`, `resources/views/quotations/{index,create,show}.blade.php`
  - `routes/web.php`（quotations.*ルート追加）, `app/Providers/AppServiceProvider.php`（DIバインド追加）
- テストファイル:
  - `tests/Unit/Services/QuotationServiceTest.php`
  - `tests/Unit/Repositories/QuotationRepositoryTest.php`
  - `tests/Feature/Quotations/QuotationManagementTest.php`
- テスト用Factory: `database/factories/QuotationFactory.php`, `database/factories/QuotationItemFactory.php`

## Redフェーズ（失敗するテスト作成）

### 作成日時

2026-06-08

### テストケース

quotation-testcases.md の13件すべてを実装（Unit: Service 5件・Repository 1件、Feature: 7件）。
正常系5・異常系4・境界値4の構成。詳細は `quotation-red-phase.md` を参照。

### テストコード

`tests/Unit/Services/QuotationServiceTest.php`, `tests/Unit/Repositories/QuotationRepositoryTest.php`,
`tests/Feature/Quotations/QuotationManagementTest.php` を参照。

### 期待される失敗

```
php artisan test tests/Unit/Services/QuotationServiceTest.php tests/Unit/Repositories/QuotationRepositoryTest.php tests/Feature/Quotations/QuotationManagementTest.php
→ tests: 13, passed: 0, errors: 13
```

- `Class "App\Models\Quotation" not found`（モデル未実装）
- `Target class [App\Services\QuotationService] does not exist.`（サービス未実装）
- `Target class [App\Repositories\Contracts\QuotationRepositoryInterface] does not exist.`（リポジトリ未実装）
- `Route [quotations.store] not defined.`（ルーティング・コントローラ未実装）

いずれも「未実装の機能を呼び出している」ことに起因する失敗であり、Redフェーズとして正しい状態。

### 次のフェーズへの要求事項

`quotation-red-phase.md`「4. Greenフェーズで実装すべき内容」に列挙した9項目（モデル・DTO・例外・Repository・Service・Controller/Request/Routing・PDFテンプレート・Blade画面・SalesOrderへのリレーション追加）を実装し、上記13件のテストをすべて成功させる。

実装順序の推奨:
1. モデル（Quotation, QuotationItem, DocumentSequence）+ マイグレーション確認（既存マイグレーションをそのまま利用）
2. DTO（QuotationData, QuotationItemData）
3. 例外（InsufficientStockException）
4. Repository（QuotationRepositoryInterface, EloquentQuotationRepository）+ AppServiceProviderバインド
5. Service（QuotationService: create, confirmToOrder, 採番ロジック）
6. Controller, FormRequest, ルーティング
7. PDFテンプレート, Bladeビュー

## Greenフェーズ（最小実装）

### 実装日時

2026-06-08

### 実装方針

Controller → Service → Repository（インターフェース＋Eloquent実装）構成で、
`ProductRepository::adjustStock()`の`DB::transaction()`＋`lockForUpdate()`パターンを踏襲して実装。
「とりあえず動く」レベルを優先し、最適化や共通化はRefactorフェーズに先送りした。
詳細は `quotation-green-phase.md` を参照。

### 実装コード

主な成果物（詳細・全文は `quotation-green-phase.md` を参照）:

- モデル/DTO/例外: `Quotation`, `QuotationItem`, `SalesOrderItem`, `DocumentSequence`, `SalesOrder`（更新）, `QuotationData`, `QuotationItemData`, `InsufficientStockException`
- リポジトリ: `QuotationRepositoryInterface`, `QuotationRepository`（`issueQuotationNumber()`で`document_sequences`を`lockForUpdate`排他制御し`QUO-{年度}-{連番4桁}`を採番）
- サービス: `QuotationService`（`create()`, `confirmToOrder()` で在庫ロック・在庫不足判定・在庫引当・受注作成をトランザクション内で実施、`issueOrderNumber()`で`ORD-{年度}-{連番4桁}`を独自設計で採番）
- リクエスト/コントローラ/ルーティング: `StoreQuotationRequest`, `QuotationController`（index/create/store/show/pdf/confirm/calculate）, `routes/web.php`
- ビュー/PDF/フロントエンド: `quotations/{index,create,show}.blade.php`, `pdf/quotations/show.blade.php`, `resources/js/quotations.js`（jQuery動的明細行UI＋`/api/internal/quotations/calculate`連携）, `vite.config.js`

### テスト結果

```
php artisan test tests/Unit/Services/QuotationServiceTest.php tests/Unit/Repositories/QuotationRepositoryTest.php tests/Feature/Quotations/QuotationManagementTest.php
Tests: 13 passed (69 assertions)

php artisan test
Tests: 103 passed, 2 skipped (393 assertions)  ※既存スキップ2件は本タスクと無関係

npm run build
✓ built in 1.48s
```

全13件のRedフェーズテストが1回の実装で通過し、既存テストへの回帰もなし。

### 課題・改善点

- `issueQuotationNumber()`と`issueOrderNumber()`のロジック重複の共通化
- `QuotationService::confirmToOrder()`のメソッド分割（可読性向上）
- `resources/js/quotations.js`のブラウザでの動作確認（E2E未実施）
- 詳細画面の金額表示形式（`assertSee('8000')`に合わせカンマ区切りなしで表示している点の改善余地）

詳細は `quotation-green-phase.md` を参照。

## Refactorフェーズ（品質改善）

### リファクタ日時

2026-06-08

### 改善内容

1. **採番ロジックの共通化**: `QuotationRepository::issueQuotationNumber()`と
   `QuotationService::issueOrderNumber()`に存在していた`document_sequences`の
   `firstOrCreate`＋`lockForUpdate`＋インクリメントのロジック重複を、
   `DocumentSequence::issueNextNumber(DocumentType $documentType, int $fiscalYear): int`
   という静的メソッドに集約した。両メソッドは採番された連番を受け取り、
   それぞれの帳票番号フォーマット（`QUO-`/`ORD-`）への変換のみを担うようになった。
2. **`QuotationService::confirmToOrder()`のメソッド分割**: 1つの長いクロージャに
   詰め込まれていた「在庫充足チェック」「在庫引当・変動履歴記録」「受注作成」の3工程を
   `lockProductsWithSufficientStock()`, `reserveStockForItems()`, `createConfirmedSalesOrder()`
   という意図の明確なプライベートメソッドへ分割し、`confirmToOrder()`本体は
   トランザクション制御とステータス更新のみを担うシンプルな構成にした。

### セキュリティレビュー

- 行ロック（`lockForUpdate()`）と`DB::transaction()`によるアトミック性は変更前と同一に維持されており、
  同時実行時の二重採番・在庫引当の不整合は引き続き防止される。
- 入力値はすべて`StoreQuotationRequest`でバリデーション済みのものを使用しており、
  リファクタリングによる新たな入力経路は追加していない。

### パフォーマンスレビュー

- クエリ発行回数・ロック取得順序はリファクタリング前と同一（メソッド分割は処理順序を変えていない）。
- `DocumentSequence::issueNextNumber()`への集約により、見積番号・受注番号の採番は
  同じ実装を共有するため、将来的な調整箇所が一箇所に絞られ保守性が向上した。

### 最終コード

詳細・全文は `quotation-green-phase.md`（Refactor後の最終構成として更新済み）と
`app/Models/DocumentSequence.php`, `app/Repositories/Eloquent/QuotationRepository.php`,
`app/Services/QuotationService.php` を参照。

### テスト結果

```
php artisan test tests/Unit/Services/QuotationServiceTest.php tests/Unit/Repositories/QuotationRepositoryTest.php tests/Feature/Quotations/QuotationManagementTest.php
Tests: 13 passed (69 assertions)

php artisan test
Tests: 103 passed, 2 skipped (393 assertions)
```

リファクタリング前後で全テストが変わらず成功し、回帰がないことを確認した。

### 品質評価

- ✅ 採番ロジックの重複が解消され、`DocumentSequence`モデルに採番処理の責務が集約された
- ✅ `confirmToOrder()`が「全体の流れ」を見渡しやすい構成になり、各工程が単独でテスト・修正しやすくなった
- ✅ 既存の悲観的ロック・トランザクション境界・例外スロー位置は変更しておらず、安全性は維持
- ⚠️ `resources/js/quotations.js`のブラウザ動作確認（E2E）は本フェーズでも未実施のため、今後の課題として残る
