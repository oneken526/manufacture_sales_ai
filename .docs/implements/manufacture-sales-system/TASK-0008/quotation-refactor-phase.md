# TDD Refactorフェーズ記録: 見積管理機能（作成・PDF・受注転換）

## リファクタ日時

2026-06-08

## 対象とした課題（Greenフェーズからの申し送り）

1. `QuotationRepository::issueQuotationNumber()`と`QuotationService::issueOrderNumber()`の
   採番ロジック（`document_sequences`の`firstOrCreate`＋`lockForUpdate`＋インクリメント）の重複
2. `QuotationService::confirmToOrder()`が「ロック取得 → 在庫充足チェック → 在庫引当・変動履歴記録 → 受注作成 → ステータス更新」
   の5工程を1つの長いクロージャに詰め込んでおり、可読性・テスト容易性に課題があった

## 実施した改善

### 1. 採番ロジックの共通化

`app/Models/DocumentSequence.php`に静的メソッド`issueNextNumber(DocumentType $documentType, int $fiscalYear): int`を追加し、
行ロックを伴う採番処理そのものをモデルに集約した。

```php
public static function issueNextNumber(DocumentType $documentType, int $fiscalYear): int
{
    static::query()->firstOrCreate(
        ['document_type' => $documentType->value, 'fiscal_year' => $fiscalYear],
        ['last_number' => 0],
    );

    $sequence = static::query()
        ->where('document_type', $documentType->value)
        ->where('fiscal_year', $fiscalYear)
        ->lockForUpdate()
        ->firstOrFail();

    $nextNumber = $sequence->last_number + 1;
    $sequence->update(['last_number' => $nextNumber]);

    return $nextNumber;
}
```

呼び出し側（`QuotationRepository::issueQuotationNumber()` / `QuotationService::issueOrderNumber()`）は、
発行された連番を受け取って帳票番号特有のフォーマット（`QUO-{年度}-{連番4桁}` / `ORD-{年度}-{連番4桁}`）に
変換するだけのシンプルな実装になった。

```php
// QuotationRepository
public function issueQuotationNumber(int $year): string
{
    $nextNumber = DocumentSequence::issueNextNumber(DocumentType::QUOTATION, $year);
    return sprintf('QUO-%d-%04d', $year, $nextNumber);
}

// QuotationService
private function issueOrderNumber(int $year): string
{
    $nextNumber = DocumentSequence::issueNextNumber(DocumentType::ORDER, $year);
    return sprintf('ORD-%d-%04d', $year, $nextNumber);
}
```

### 2. `confirmToOrder()`のメソッド分割

トランザクション内の処理を意図が明確な3つのプライベートメソッドへ分割した。

- `lockProductsWithSufficientStock(Quotation $locked): array`
  全明細の対象製品を行ロックし、利用可能在庫（`stock_quantity - reserved_quantity`）が
  要求数量を満たすか検証する。不足があれば`InsufficientStockException`を即座にスローする（EDGE-001）。
  ロック済み製品を`quotation_item.id`をキーとした連想配列で返し、後続処理での再ロックを避ける。
- `reserveStockForItems(Quotation $locked, array $lockedProducts): void`
  在庫充足が確認済みの明細について`reserved_quantity`を加算し、`stock_movements`
  （`reason = RESERVATION`）を記録する。
- `createConfirmedSalesOrder(Quotation $locked): SalesOrder`
  見積明細を引き継いだ確定済み受注（`sales_orders`/`sales_order_items`）を作成する。

`confirmToOrder()`本体は以下のように、トランザクション制御と全体の流れ、最後のステータス更新のみを担う形になった。

```php
public function confirmToOrder(Quotation $quotation): void
{
    DB::transaction(function () use ($quotation) {
        $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
        $locked->load('items');

        $lockedProducts = $this->lockProductsWithSufficientStock($locked);
        $this->reserveStockForItems($locked, $lockedProducts);
        $this->createConfirmedSalesOrder($locked);

        $locked->update(['status' => QuotationStatus::CONVERTED]);
    });
}
```

## セキュリティレビュー

- 行ロック（`lockForUpdate()`）と`DB::transaction()`によるアトミック性・排他制御は変更前と完全に同一であり、
  同時実行時の二重採番・在庫引当の不整合リスクは増減していない。
- 例外（`InsufficientStockException`）のスロー位置・タイミングも変更しておらず、
  在庫不足時に途中までの更新がロールバックされる安全性（EDGE-001）は維持されている。
- 入力値の経路（`StoreQuotationRequest`によるバリデーション）に変更はなく、新たな攻撃面は追加していない。

## パフォーマンスレビュー

- SQLクエリの発行回数・実行順序はリファクタリング前と同一（メソッド分割は処理の入れ替えを伴わない）。
- `DocumentSequence::issueNextNumber()`への共通化により、見積番号・受注番号の採番処理は
  同一の実装を共有するようになり、将来インデックスやロック戦略を見直す際の修正箇所が一本化された。

## テスト実行結果

```
php artisan test tests/Unit/Services/QuotationServiceTest.php tests/Unit/Repositories/QuotationRepositoryTest.php tests/Feature/Quotations/QuotationManagementTest.php
Tests: 13 passed (69 assertions)

php artisan test
Tests: 103 passed, 2 skipped (393 assertions)
```

リファクタリング前後でテスト結果に変化はなく、全件成功・回帰なしを確認した。

## 品質評価

| 項目 | 評価 | 備考 |
| --- | --- | --- |
| テスト成功状況 | ✅ | 対象13件・全体103件とも成功（リファクタ前後で変化なし） |
| 可読性・保守性 | ✅ | 採番ロジックの重複解消、`confirmToOrder()`の工程分割により見通しが改善 |
| セキュリティ | ✅ | ロック・トランザクション境界・例外スロー位置を変更せず安全性を維持 |
| パフォーマンス | ✅ | クエリ発行回数・順序に変化なし |
| モック使用 | ✅ | 実装コードにモック・スタブは含まれていない |

## 後続タスクへの申し送り事項

- `resources/js/quotations.js`によるjQuery動的明細行UI（行追加・削除・単価自動補完・
  `/api/internal/quotations/calculate`によるリアルタイム金額再計算）はユニット/統合テストの対象外であり、
  ブラウザでの手動確認・E2Eテスト（Playwright等）の追加が望ましい
- 詳細画面（`quotations/show.blade.php`）の金額表示は、統合テストの`assertSee('8000')`
  （カンマ区切りなしの完全一致的な部分文字列検証）に合わせてカンマ区切りを使わない表示としている。
  将来的にUI上の可読性向上のためカンマ区切り表示へ変更する場合は、テスト側のアサーション
  （`assertSee('8,000')`への変更、または`assertSeeText`等への切り替え）も合わせて見直す必要がある
- 受注番号フォーマット（`ORD-{年度}-{連番4桁}`）は本タスクでの独自設計であるため、
  別タスクで受注管理機能（一覧・詳細など）を実装する際は、このフォーマットとの整合性を確認すること
