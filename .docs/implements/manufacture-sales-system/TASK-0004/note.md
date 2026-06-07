# TASK-0004 開発コンテキストノート

## 1. 技術スタック
- Laravel 13 (PHP 8.3) + mpdf/mpdf ^8.3
- Bladeテンプレートエンジン → mPDFでHTML→PDF変換
- 例外処理は Laravel 13 の `bootstrap/app.php` の `->withExceptions(...)` パターン
- 参照元: composer.json, bootstrap/app.php

## 2. 開発ルール
- レスポンスは日本語（CLAUDE.md）
- テストは PHPUnit 属性（`#[DataProvider(...)]`等）を使用、アノテーションは使わない
- テストには 【テスト目的】【テスト内容】【期待される動作】 のコメントブロックと信頼性レベル(🔵🟡🔴)を付与する
- `RefreshDatabase` トレイトを使用（DB絡みのテストの場合）
- 参照元: tests/Feature/Authorization/InvoiceGateTest.php, tests/Unit/EnumsTest.php, CLAUDE.md

## 3. 関連実装
- **app/Services/PdfService.php**（既存スタブ、要拡張）
  - `fromView(string $view, array $data = []): Mpdf` — Bladeをレンダリングして mPDF インスタンスを返す
  - `download(string $view, array $data, string $filename)` — PDFダウンロードレスポンスを返す
  - 不足: `generateFromView()`（バイナリ/保存パスを返す）、ファイル名・パス生成ヘルパー、保存処理、例外ハンドリング
- **resources/views/pdf/sample.blade.php** — 日本語フォント(meiryo)動作確認用の簡易テンプレート（参考にできる）
- **config/mpdf.php** — mPDF設定済み（日本語フォント meiryo、tempDir: storage/app/mpdf）
- 参照元: app/Services/PdfService.php, resources/views/pdf/sample.blade.php, config/mpdf.php

## 4. 設計文書
- **architecture.md**: `Services/PdfService.php # mPDFラッパー`、`resources/views/pdf/ # mPDF用テンプレート`、PDF生成は将来キュー非同期化を検討
- **dataflow.md**: 出荷完了時に ShipmentService → PdfService（納品書PDF）、請求書発行時に InvoiceService → PdfService（請求書PDF）という呼び出しフローが記載
- **requirements.md**:
  - REQ-032: 「システムは見積のPDFプレビュー・ダウンロードができなければならない」🟡
  - REQ-052: 「システムは納品書をPDF出力できなければならない」🔵
  - REQ-061: 「請求書はPDF形式でダウンロードできなければならない」🔵
  - EDGE-003: 「PDF生成に失敗した場合、システムはエラーメッセージを表示して再試行を促さなければならない」🟡
- **api-endpoints.md**: GET `/quotations/{quotation}/pdf`、GET `/shipments/{shipment}/delivery-note`、GET `/invoices/{invoice}/pdf`
- 参照元: .docs/design/manufacture-sales-system/architecture.md, .docs/design/manufacture-sales-system/dataflow.md, .docs/spec/manufacture-sales-system/requirements.md, .docs/design/manufacture-sales-system/api-endpoints.md

## 5. テスト関連情報
- `phpunit.xml`: Unit/Featureの2スイート、テスト用にSQLiteインメモリDB
- 既存テスト例: tests/Feature/Authorization/InvoiceGateTest.php（DataProvider駆動）, tests/Unit/EnumsTest.php（直接アサーション）, tests/Feature/Auth/AuthenticationTest.php
- 本タスクではDB非依存（PDF生成のみ）のため `Tests\TestCase` を継承する Unit/Feature テストとして作成可能
- 参照元: phpunit.xml, tests/Feature/Authorization/InvoiceGateTest.php, tests/Unit/EnumsTest.php

## 6. 注意事項
- **例外構造**: `app/Exceptions/` ディレクトリは未作成。Laravel 13 の `bootstrap/app.php` の `->withExceptions(function (Exceptions $exceptions) {...})` パターンで例外をレンダリング/登録する
- **会社情報設定**: `config/company.php` は未作成。要件に沿って新規作成する必要がある
- **ストレージ**: `storage/app/private`（localディスク）, `storage/app/public`（publicディスク、`/storage`シンボリックリンク経由で公開）, `storage/app/mpdf`（mPDFのテンポラリ・フォントキャッシュ用、既存）
- **日本語フォント**: 開発環境ではWindowsのMeiryoフォント（`C:\Windows\Fonts`）を参照する設定。本番環境ではIPAex Gothic等への切替が必要（config/mpdf.phpにコメント記載済み）
- **個人情報**: 例外メッセージ・ログ出力に個人情報（顧客情報等）を含めないこと
- 参照元: bootstrap/app.php, config/filesystems.php, config/mpdf.php
