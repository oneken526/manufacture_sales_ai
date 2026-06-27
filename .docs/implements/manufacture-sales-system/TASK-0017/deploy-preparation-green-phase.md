# 実装記録（Green Phase）— TASK-0017: 最終調整・本番デプロイ準備

**実施日**: 2026-06-27  
**タイプ**: DIRECTタスク（TDDではなく直接実装）

---

## 実装方針

本タスクは環境構築・確認作業（DIRECTタスク）のため、TDDではなく直接実装を行う。

---

## 実装内容

### 1. DB互換性修正（`app/Services/ReportService.php`）

**課題**: `aggregateYearly()` で MySQL 専用の `DATE_FORMAT()` を使用していたため、SQLiteテスト環境で動作しなかった。

**対応**: `match(DB::getDriverName())` で各DBドライバの日付関数に切り替える実装に変更。

```php
$yearMonthExpr = match (DB::getDriverName()) {
    'sqlite' => "strftime('%Y-%m', sales_orders.confirmed_at) as label",
    'pgsql'  => "TO_CHAR(sales_orders.confirmed_at, 'YYYY-MM') as label",
    default  => "DATE_FORMAT(sales_orders.confirmed_at, '%Y-%m') as label",
};
```

### 2. バックアップコマンド（`app/Console/Commands/DatabaseBackup.php`）

`spatie/laravel-backup` が未導入のため、カスタム Artisan コマンドとして実装。

- `backup:run` コマンド
- MySQL: `mysqldump`, PostgreSQL: `pg_dump`, SQLite: `copy()` で対応
- バックアップ先: `storage/backups/backup_YYYY-MM-DD_HHiiss.sql`
- `--keep=N` オプションで古いバックアップを自動削除

### 3. スケジュール登録（`routes/console.php`）

```php
Schedule::command('backup:run --keep=7')->daily()->at('02:00');
```

サーバーcron設定: `* * * * * php artisan schedule:run >> /dev/null 2>&1`

### 4. 本番設定テンプレート（`.env.production.example`）

必須項目を整備：
- `APP_ENV=production`, `APP_DEBUG=false`（NFR-013）
- DB接続設定（MySQL / PostgreSQL コメントアウト切り替え形式）
- `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`
- `LOG_LEVEL=warning`
- `ANTHROPIC_API_KEY` プレースホルダー（Phase 2向け）

### 5. 運用文書

- `.docs/operations/secrets-management.md`: シークレット管理方針 + デプロイ前チェックリスト
- `.docs/operations/performance-check-report.md`: NFR-001/002 パフォーマンス確認レポート

---

## テスト実行結果

DB互換性修正後のテスト結果：

```
Tests: 182 total, 180 passed, 2 skipped, 0 failed
```

全テストがSQLiteテスト環境で通過。

---

## 課題と対応

| 課題 | 対応 |
|------|------|
| `DATE_FORMAT` がSQLite非対応 | `match(DB::getDriverName())` で分岐 |
| `spatie/laravel-backup` 未インストール | カスタムArtisanコマンドで代替実装 |
| バックアップのクラウド転送未実装 | 本番環境の要件に応じて別途設定するよう注記 |

---

## 申し送り事項

- 本番デプロイ前に `.docs/operations/secrets-management.md` のチェックリストを必ず実施すること
- `storage/backups/` を `.gitignore` に追加することを推奨
- 本番環境で `php artisan backup:run` を手動実行し動作確認を行うこと
- バックアップのクラウドストレージへの転送（AWS S3等）が必要な場合は、`DatabaseBackup.php` にアップロード処理を追加するか `spatie/laravel-backup` を導入すること
