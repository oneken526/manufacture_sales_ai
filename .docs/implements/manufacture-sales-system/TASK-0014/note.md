# TASK-0014 開発コンテキストノート: 売上レポート機能

## 技術スタック
- Laravel 13 / PHP 8.4
- Tailwind CSS + Bootstrap 5（既存プロジェクトに準拠）
- Chart.js 4.4 (CDN経由) - グラフ描画
- PHPUnit (RefreshDatabase属性)

## アーキテクチャ
- `ReportController → ReportService → DB（Query Builder）`
- Repository層は省略（集約クエリのみのためServiceに直接記述）

## 重要な設計決定

### カラム名
- `customers.company_name`（`name` ではない）
- `products.product_name`（`name` ではない）

### 集計対象ステータス
キャンセル（5）・返品済み（6）を除外:
```php
whereNotIn('sales_orders.status', [OrderStatus::CANCELLED->value, OrderStatus::RETURNED->value])
```

### 日付フィルタ
`sales_orders.confirmed_at` を基準に `whereYear` / `whereMonth` でフィルタ

### CSVエクスポート
`StreamedResponse` + BOM付きUTF-8（Excel対応）

## 実装ファイル
- `app/DataTransferObjects/SalesReportData.php`
- `app/Services/ReportService.php`
- `app/Http/Controllers/ReportController.php`
- `resources/views/reports/sales.blade.php`
- `routes/web.php`（`reports.sales`, `reports.sales.export`追加）

## テスト結果
- 単体テスト: 2ケース (ReportServiceTest)
- 統合テスト: 5ケース (ReportManagementTest)
- 合計: 7ケース全通過、全体163テスト通過
