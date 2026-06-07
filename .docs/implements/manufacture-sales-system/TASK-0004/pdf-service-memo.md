# TDD開発メモ: PDFサービス基盤実装（TASK-0004）

## 概要

- 機能名: pdf-service（PDFサービス基盤: mPDFラッパー・帳票共通テンプレート）
- 開発開始: 2026-06-07
- 現在のフェーズ: ✅ 完了（Red→Green→Refactor→Verify-Complete 全フェーズ完了）

## 🎯 最終検証結果（2026-06-07）

- **実装率**: 100%（11/11テストケース）
- **テスト成功率**: 100%（PdfService関連11件、全体69/71件・スキップ2件は本タスクと無関係なDatabaseSchemaTestの既存スキップ）
- **要件網羅率**: 100%（REQ-032/REQ-052/REQ-061/EDGE-003、完了条件7項目すべて実装・テスト済み）
- **品質判定**: ✅ 合格（高品質）
- **元タスクファイル**: `.docs/tasks/manufacture-sales-system/TASK-0004.md` に完了マーク追加済み

## 💡 重要な技術学習

### 実装パターン
- mPDFインスタンスをサービスプロバイダでコンテナバインド（非singleton）し、`app(Mpdf::class)`経由で解決することで、本番では`config('mpdf')`設定を適用しつつテストでは`$this->app->bind(Mpdf::class, fn () => $mock)`で容易にモック差し替え可能にした
- 独自例外（`PdfGenerationException`）にビュー名と元例外をラップし、ユーザー向け固定メッセージ（再試行促進）とログ用詳細情報を分離する設計はEDGE-003系の要件に再利用できる
- `Storage::fake('local')`を使った統合テストでファイルシステムを汚さずに保存・再取得フローを検証するパターン

### テスト設計
- `smalot/pdfparser`（dev依存）でPDFバイナリからテキスト抽出し、日本語フォント設定の正しさをアサーションで検証する手法は、後続の帳票PDF出力タスクでも再利用できる
- PHPUnit属性`#[DataProvider]`で帳票種別ごとの命名規則テストをパラメータ化し、3種別を1テストメソッドで網羅

### 品質保証
- ログ出力に顧客情報（`$data`）を含めず、テンプレート名と例外オブジェクトのみを記録する設計を徹底（個人情報配慮）
- `bootstrap/app.php`の`withExceptions`に独自例外のレンダリングハンドラを登録し、`back()->with('pdf_error', ...)`でフラッシュメッセージ表示の共通基盤を整備（後続タスクのControllerが個別実装不要）

## 📌 後続タスクへの引き継ぎ事項

- `PdfService::generateAndStore()`/`buildStoragePath()`/`download()`/`generateFromView()`は帳票種別非依存の汎用APIとして利用可能
- 各帳票テンプレート（見積/納品/請求）は`@extends('pdf.layouts.base')`で共通レイアウトを継承する
- 会社情報は`config('company.*')`から取得（`.env`で上書き可能）
- 画面側のフラッシュメッセージ表示は`session('pdf_error')`を参照する想定（共通レイアウト・各帳票画面のBladeで表示処理を実装すること）

## 関連ファイル

- 元タスクファイル: `.docs/tasks/manufacture-sales-system/TASK-0004.md`
- 要件定義: `.docs/implements/manufacture-sales-system/TASK-0004/pdf-service-requirements.md`
- テストケース定義: `.docs/implements/manufacture-sales-system/TASK-0004/pdf-service-testcases.md`
- Redフェーズ記録: `.docs/implements/manufacture-sales-system/TASK-0004/pdf-service-red-phase.md`
- 実装ファイル: `app/Services/PdfService.php`, `app/Exceptions/PdfGenerationException.php`,
  `resources/views/pdf/layouts/base.blade.php`, `resources/views/pdf/layouts/style.blade.php`, `config/company.php`
- テストファイル: `tests/Unit/Services/PdfServiceTest.php`, `tests/Feature/PdfServiceIntegrationTest.php`

## Redフェーズ（失敗するテスト作成）

### 作成日時

2026-06-07

### テストケース

- TC1: BladeビューからPDFバイナリを生成できる（🔵）
- TC2: 日本語が文字化けせずPDFに表示される（🔵）
- TC3/TC4: ファイル名・保存パスが命名規則どおりに生成される（quotation/shipment/invoice）（🟡）
- TC5: 共通レイアウトでのPDF生成→保存→再取得の統合フロー（🟡）
- TC6: 存在しないビュー名で例外がスローされる（🟡）
- TC7: テンプレートレンダリング失敗時に例外がスローされる（🟡）
- TC8: 生成失敗の伝播・ログ記録（モック）（🟡）
- TC9: 空データでも静的テンプレートなら生成できる（🟡）
- TC10: 年度境界でもパス生成が冪等に動作する（🔴: 年度決定ロジックは推測）

### テストコード

- `tests/Unit/Services/PdfServiceTest.php`（9テスト）
- `tests/Feature/PdfServiceIntegrationTest.php`（2テスト）

### 期待される失敗

- `generateFromView()` / `buildStoragePath()` / `generateAndStore()` が未実装のため `Call to undefined method`
- `PdfGenerationException` クラスが未作成のため `Class "App\Exceptions\PdfGenerationException" does not exist`

実行結果: `php artisan test --filter=PdfServiceTest` → 9件中9件失敗、`--filter=PdfServiceIntegrationTest` → 2件中2件失敗（想定どおり）

### 次のフェーズへの要求事項

詳細は `pdf-service-red-phase.md` の「Greenフェーズで実装すべき内容」を参照。要点:
1. `PdfGenerationException`（再試行を促すメッセージ・元例外保持）
2. `PdfService::generateFromView/buildStoragePath/generateAndStore` の実装
3. 共通レイアウト（`pdf/layouts/base.blade.php`, `style.blade.php`）と `config/company.php`

## Greenフェーズ（最小実装）

### 実装日時

2026-06-07

### 実装方針

- `PdfGenerationException`（再試行を促すメッセージ＋元例外chain）を新規作成
- `AppServiceProvider`に`Mpdf::class`のコンテナバインドを追加し、`config('mpdf')`を適用しつつテストでモック差し替え可能にした
- `PdfService`に`generateFromView`/`buildStoragePath`/`generateAndStore`を追加（詳細は`pdf-service-green-phase.md`参照）
- 共通レイアウト（`pdf/layouts/base.blade.php`, `style.blade.php`）と`config/company.php`を新規作成

### 実装コード

詳細は `pdf-service-green-phase.md` および各実装ファイルを参照。

### テスト結果

```
php artisan test --filter=Pdf
→ {"result":"passed","tests":11,"passed":11,"assertions":31}
```

11テスト全て成功。

### 課題・改善点

`pdf-service-green-phase.md`の「課題・改善点」を参照（重複ロジックの共通化、種別バリデーション等）。

## Refactorフェーズ（品質改善）

### リファクタ日時

2026-06-07

### 改善内容

1. `download()`を`generateFromView()`へ処理委譲する形に統一し、未使用となった`fromView()`を削除（重複ロジック除去・ダウンロード時のEDGE-003対応漏れを修正）
2. `bootstrap/app.php`に`PdfGenerationException`専用のレンダリングハンドラを追加し、`back()->with('pdf_error', ...)`によるフラッシュメッセージ表示の共通基盤を整備

### セキュリティレビュー

- 個人情報（`$data`）をログに出力しない設計を確認
- `PdfGenerationException`のメッセージは固定文言で内部情報を露出しない
- 重大な脆弱性なし（詳細は`pdf-service-refactor-phase.md`参照）

### パフォーマンスレビュー

- 各メソッドはO(1)〜O(HTML描画コスト)で重大なボトルネックなし
- Mpdfは非singletonバインドで状態の使い回しを回避（妥当な設計）

### 最終コード

`app/Services/PdfService.php`, `app/Exceptions/PdfGenerationException.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`

### 品質評価

✅ 高品質。全PDFテスト11件、全体テスト69件（+スキップ2件、本タスクと無関係）が継続成功。詳細は`pdf-service-refactor-phase.md`参照。
