# 開発コンテキストノート — TASK-0017: 最終調整・本番デプロイ準備

## 技術スタック

- **フレームワーク**: Laravel 13 / PHP 8.4
- **本番DB**: MySQL または PostgreSQL（NFR-030）
- **開発DB**: SQLite（テスト用）
- **スケジューラー**: Laravel Scheduler（`routes/console.php` / `Schedule::command()`）
- **バックアップ**: カスタム Artisan コマンド `backup:run`（`app/Console/Commands/DatabaseBackup.php`）

## 主要な実装判断

### 1. DB互換性対応（ReportService）

`aggregateYearly()` で使用していた MySQL 専用の `DATE_FORMAT()` を、
`match(DB::getDriverName())` で SQLite / PostgreSQL / MySQL に自動切り替えする実装に変更した。
これにより `php artisan test` がSQLiteテスト環境でも失敗しなくなる。

```php
$yearMonthExpr = match (DB::getDriverName()) {
    'sqlite' => "strftime('%Y-%m', sales_orders.confirmed_at) as label",
    'pgsql'  => "TO_CHAR(sales_orders.confirmed_at, 'YYYY-MM') as label",
    default  => "DATE_FORMAT(sales_orders.confirmed_at, '%Y-%m') as label",
};
```

### 2. バックアップ戦略

`spatie/laravel-backup` は未インストールのため、シンプルなカスタムコマンドを実装。
- MySQL: `mysqldump` コマンドを `exec()` で実行
- PostgreSQL: `pg_dump` コマンドを `exec()` で実行
- SQLite: `copy()` でファイルをコピー
- バックアップ先: `storage/backups/`
- 世代管理: `--keep=7`（デフォルト7世代）
- スケジュール: 毎日午前2時実行

### 3. シークレット管理

`.env.production.example` に本番設定テンプレートを整備。
実際の値はサーバー環境変数・シークレットマネージャー経由で注入する方針を `.docs/operations/secrets-management.md` に文書化。

## 成果物一覧

| ファイル | 種別 | 内容 |
|---------|------|------|
| `app/Services/ReportService.php` | 修正 | `aggregateYearly()` をDB非依存化 |
| `app/Console/Commands/DatabaseBackup.php` | 新規 | 日次バックアップコマンド |
| `routes/console.php` | 修正 | 日次バックアップスケジュール登録 |
| `.env.production.example` | 新規 | 本番環境設定テンプレート |
| `.docs/operations/secrets-management.md` | 新規 | シークレット管理方針 |
| `.docs/operations/performance-check-report.md` | 新規 | NFR-001/002 確認レポート |

## 注意事項

- バックアップファイル（`storage/backups/*.sql`）を Git にコミットしないこと。`.gitignore` に `storage/backups/` を追加することを推奨
- 本番環境では `crontab -e` で `* * * * * php artisan schedule:run >> /dev/null 2>&1` を設定すること
- `APP_DEBUG=false` の設定漏れは重大なセキュリティリスク（NFR-013）
