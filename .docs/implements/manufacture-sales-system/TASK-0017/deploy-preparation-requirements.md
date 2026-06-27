# 要件整理 — TASK-0017: 最終調整・本番デプロイ準備

## 1. 機能の概要

🟡 本タスクは機能追加ではなく、NFR（非機能要件）充足のための環境構築・確認作業である。

- **DB互換性確保**: 開発環境（SQLite）と本番環境（MySQL/PostgreSQL）の差異を吸収する
- **バックアップ自動化**: 日次DBバックアップを Laravel Scheduler で実行
- **シークレット管理**: 本番環境設定テンプレートとシークレット管理方針の文書化
- **パフォーマンス確認**: NFR-001（3秒以内）・NFR-002（10秒以内）の充足確認

参照要件: NFR-030, NFR-031, NFR-001, NFR-002, NFR-013

## 2. 完了条件チェックリスト

### 2-1. DB互換性（NFR-030）

- [x] `ReportService::aggregateYearly()` のDB固有SQL（`DATE_FORMAT`）を `match(DB::getDriverName())` で解消
- [x] SQLite / MySQL / PostgreSQL すべてで動作するよう `strftime` / `DATE_FORMAT` / `TO_CHAR` を切り替え
- [x] `php artisan test` が全テストパス（SQLite環境）

### 2-2. バックアップ（NFR-031）

- [x] `app/Console/Commands/DatabaseBackup.php` でカスタムバックアップコマンドを実装
- [x] MySQL / PostgreSQL / SQLite の各ドライバに対応
- [x] `routes/console.php` で `backup:run` を毎日 02:00 に実行するスケジュール登録
- [x] `--keep=7` オプションで7世代の世代管理を実装

### 2-3. シークレット管理（NFR-013）

- [x] `.env.production.example` に必須項目（APP_KEY, APP_DEBUG=false, DB設定, ANTHROPIC_API_KEY等）を整備
- [x] `.docs/operations/secrets-management.md` にシークレット管理方針とデプロイ前チェックリストを文書化

### 2-4. パフォーマンス確認（NFR-001, NFR-002）

- [x] `.docs/operations/performance-check-report.md` に実装済みの最適化（ページネーション、Eager Loading、集約クエリ）を記録
- [x] DB互換性修正後も全テストが通ること（182テスト全通過）

## 3. アーキテクチャ上の制約

🟡 *architecture.md の DB互換性要件、NFR-030より*

- 本番DB: MySQL 8.0以上 または PostgreSQL 14以上
- ORM: Eloquent Query Builder 使用（素のSQL直書きを最小化）
- DB固有関数を使う場合: `DB::getDriverName()` で分岐すること
- バックアップ保存先: `storage/backups/`（本番はクラウドストレージへの転送を推奨）

## 4. 実装ファイル一覧

| ファイル | 種別 | 根拠 |
|---------|------|------|
| `app/Services/ReportService.php` | 修正 | NFR-030 DB互換性 |
| `app/Console/Commands/DatabaseBackup.php` | 新規 | NFR-031 バックアップ |
| `routes/console.php` | 修正 | NFR-031 スケジューラー |
| `.env.production.example` | 新規 | NFR-030 本番設定テンプレート |
| `.docs/operations/secrets-management.md` | 新規 | NFR-013 シークレット管理方針 |
| `.docs/operations/performance-check-report.md` | 新規 | NFR-001, NFR-002 確認記録 |
