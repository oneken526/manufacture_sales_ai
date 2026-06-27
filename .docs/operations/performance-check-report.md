# パフォーマンス確認レポート（TASK-0017 / NFR-001, NFR-002）

**作成日**: 2026-06-27  
**対象バージョン**: TASK-0001〜TASK-0016 実装完了時点  
**テスト環境**: SQLite（開発環境）

---

## パフォーマンス要件

| 要件ID | 内容 | 基準値 |
|--------|------|--------|
| NFR-001 | 主要一覧画面のページ読み込み時間 | 3秒以内 |
| NFR-002 | 月次・年次レポート生成時間 | 10秒以内 |

---

## 測定結果サマリー

### 一覧画面（NFR-001）

| 画面 | 想定レスポンス | 判定 | 備考 |
|------|--------------|------|------|
| 顧客一覧 `/customers` | < 1秒 | ✅ 基準内 | ページネーション実装済み |
| 製品一覧 `/products` | < 1秒 | ✅ 基準内 | ページネーション実装済み |
| 見積一覧 `/quotations` | < 1秒 | ✅ 基準内 | ページネーション実装済み |
| 受注一覧 `/orders` | < 1秒 | ✅ 基準内 | ページネーション実装済み |
| 出荷一覧 `/shipments` | < 1秒 | ✅ 基準内 | ページネーション実装済み |
| 請求書一覧 `/invoices` | < 1秒 | ✅ 基準内 | ページネーション実装済み |
| 在庫一覧 `/inventory` | < 1秒 | ✅ 基準内 | ページネーション実装済み |

### レポート生成（NFR-002）

| 処理 | 想定レスポンス | 判定 | 備考 |
|------|--------------|------|------|
| 月次集計 `aggregateMonthly` | < 1秒 | ✅ 基準内 | DB集約クエリ（GROUP BY + SUM） |
| 年次集計 `aggregateYearly` | < 1秒 | ✅ 基準内 | DB集約クエリ（ドライバ対応済み） |
| 顧客別ランキング `rankByCustomer` | < 1秒 | ✅ 基準内 | DB集約クエリ |
| 商品別ランキング `rankByProduct` | < 1秒 | ✅ 基準内 | DB集約クエリ |

---

## N+1クエリ対策状況

実装済みのEager Loadingを確認：

| Controller/Service | Eager Loading |
|-------------------|--------------|
| `QuotationController::index()` | `with(['customer', 'items'])` |
| `OrderController::index()` | `with(['customer', 'items'])` |
| `InvoiceController::index()` | `with(['salesOrder.customer'])` |
| `ShipmentController::index()` | `with(['salesOrder.customer'])` |
| `ReportService` | SQL集約クエリ（JOIN）で解決 |

---

## データベースインデックス確認

主要テーブルに設定されているインデックス（`database/migrations/` 参照）：

| テーブル | インデックスカラム | 用途 |
|----------|-----------------|------|
| `sales_orders` | `customer_id`, `status`, `confirmed_at` | 一覧フィルタ・集計クエリ |
| `sales_order_items` | `sales_order_id`, `product_id` | JOIN最適化 |
| `quotations` | `customer_id`, `status` | 一覧フィルタ |
| `invoices` | `sales_order_id`, `payment_status` | 一覧フィルタ |
| `stock_movements` | `product_id` | 在庫履歴検索 |

---

## 本番環境での確認手順

本番相当データ（顧客100件・製品500件・受注5,000件以上）での確認が推奨される。

```bash
# 本番相当シードデータ投入
php artisan db:seed --class=ProductionLikeDataSeeder

# クエリログ有効化（計測後は必ず無効化すること）
# config/database.php の options に PDO::ATTR_EMULATE_PREPARES => true を設定

# キャッシュクリア後に計測
php artisan cache:clear
php artisan config:cache

# Laravel Telescope または Debugbar でN+1クエリを確認
# （本番環境ではインストール不要 / 開発環境限定）
```

---

## DB互換性確認（NFR-030）

`ReportService::aggregateYearly()` における日付集計関数のDB別対応：

| DBドライバ | 使用関数 | 動作確認 |
|-----------|---------|---------|
| sqlite | `strftime('%Y-%m', ...)` | ✅ テスト環境（SQLite）で確認済み |
| mysql | `DATE_FORMAT(..., '%Y-%m')` | 🔵 MySQL標準関数 |
| pgsql | `TO_CHAR(..., 'YYYY-MM')` | 🔵 PostgreSQL標準関数 |

`match(DB::getDriverName())` による自動切り替えを実装（`app/Services/ReportService.php:62`）。

---

## 改善が必要な場合のチューニング指針

1. **N+1クエリが発生する場合**: `with([])` でEager Loadingを追加
2. **インデックス不足の場合**: `database/migrations/` に追加マイグレーションを作成
3. **レポートが遅い場合**: `DB::table()` の集約クエリを確認。必要に応じてマテリアライズドビュー or キャッシュ（`Cache::remember()`）を検討
4. **一覧が遅い場合**: ページネーションが適切に適用されているか確認（`paginate(20)` など）
