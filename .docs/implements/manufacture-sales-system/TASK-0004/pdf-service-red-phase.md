# TASK-0004: PDFサービス基盤実装 - Redフェーズ記録

## 作成したテストファイル

- `tests/Unit/Services/PdfServiceTest.php`（TC1, TC2, TC3, TC4, TC6, TC7, TC9, TC10 / 9テスト）
- `tests/Feature/PdfServiceIntegrationTest.php`（TC5, TC8 / 2テスト）

## 作成したフィクスチャ（テスト用テンプレート）

- `resources/views/pdf/test-template.blade.php` — 日本語＋変数を含む簡易テンプレート（TC1, TC2, TC6, TC7用）
- `resources/views/pdf/static-template.blade.php` — 変数に依存しない静的テンプレート（TC9用）
- `resources/views/pdf/fixtures/sample-report.blade.php` — 共通レイアウト(`pdf.layouts.base`)を継承する統合テスト用テンプレート（TC5用）

## 追加した依存パッケージ

- `smalot/pdfparser`（dev依存）— 生成PDFからのテキスト抽出による日本語表示検証（TC2, TC5）に使用

## テストケース一覧と実行結果

| # | テストケース | テストファイル | 結果 | 失敗理由 |
|---|---|---|---|---|
| TC1 | PdfServiceがBladeビューからPDFバイナリを生成できる | PdfServiceTest::test_generate_from_view_returns_pdf_binary | ❌失敗 | `Call to undefined method App\Services\PdfService::generateFromView()` |
| TC2 | 日本語が文字化けせずPDFに表示される | PdfServiceTest::test_japanese_text_is_embedded_without_corruption | ❌失敗 | 同上（generateFromView未実装） |
| TC3/TC4 | ファイル名・保存パスが命名規則どおりに生成される（quotation/shipment/invoice） | PdfServiceTest::test_build_storage_path_follows_naming_convention（DataProvider 3件） | ❌失敗 | `Call to undefined method App\Services\PdfService::buildStoragePath()` |
| TC6 | 存在しないビュー名で例外がスローされる | PdfServiceTest::test_generate_from_view_throws_exception_for_non_existent_view | ❌失敗 | `Class "App\Exceptions\PdfGenerationException" does not exist` |
| TC7 | テンプレートレンダリング失敗時に例外がスローされる | PdfServiceTest::test_generate_from_view_throws_exception_when_template_rendering_fails | ❌失敗 | 同上（PdfGenerationException未作成） |
| TC9 | 空データでも静的テンプレートなら生成できる | PdfServiceTest::test_generate_from_view_succeeds_with_empty_data_for_static_template | ❌失敗 | `Call to undefined method App\Services\PdfService::generateFromView()` |
| TC10 | 年度境界でもパス生成が冪等に動作する | PdfServiceTest::test_build_storage_path_is_idempotent_across_year_boundaries | ❌失敗 | `Call to undefined method App\Services\PdfService::buildStoragePath()` |
| TC5 | 共通レイアウトでのPDF生成→保存→再取得の統合フロー | PdfServiceIntegrationTest::test_generate_store_and_retrieve_pdf_with_common_layout | ❌失敗 | `Call to undefined method App\Services\PdfService::generateAndStore()` |
| TC8 | 生成失敗の伝播・ログ記録（モック） | PdfServiceIntegrationTest::test_pdf_generation_failure_propagates_and_is_logged | ❌失敗 | `Class "App\Exceptions\PdfGenerationException" does not exist` |

**実行結果サマリ**: 11テスト中11テストが失敗（Redフェーズとして正しく失敗することを確認済み）

```
php artisan test --filter=PdfServiceTest
→ tests:9, passed:0, errors:9

php artisan test --filter=PdfServiceIntegrationTest
→ tests:2, passed:0, errors:2
```

## Greenフェーズで実装すべき内容

1. **`App\Exceptions\PdfGenerationException`**（`app/Exceptions/PdfGenerationException.php`）
   - 例外メッセージに「PDFの生成に失敗しました。しばらく経ってから再度お試しください」等、再試行を促す文言を含める
   - テンプレート名・元例外を保持できるようにし、`Log::error()` に詳細情報（テンプレート名・例外メッセージ・スタックトレース）を渡せるようにする

2. **`App\Services\PdfService`の拡張**（`app/Services/PdfService.php`）
   - `generateFromView(string $view, array $data = [], ?string $filename = null): string` — Bladeビューをレンダリングし、mPDFでPDFバイナリ（`Output('', 'S')`）を返す。例外発生時は`PdfGenerationException`にラップして`Log::error()`に記録する
   - `buildStoragePath(string $type, string $identifier, ?int $year = null): string` — `pdf/{帳票種別}/{年度}/{種別}_{識別子}.pdf` 形式の相対パスを返す（年度は明示指定がなければ生成時点の年を採用する方針とし、コメントで明記する）
   - `generateAndStore(string $view, array $data, string $type, string $identifier): string` — `generateFromView()`→`buildStoragePath()`→`Storage::disk('local')->put()` を実行し、保存パスを返す
   - 既存の `fromView()` / `download()` は維持しつつ、内部で `generateFromView()` を活用するよう整理する

3. **共通PDFレイアウト**（`resources/views/pdf/layouts/base.blade.php`, `resources/views/pdf/layouts/style.blade.php`）
   - ヘッダー（会社名・住所・電話番号）、フッター（ページ番号・出力日時）を含む共通レイアウト
   - `@yield('content')` で各帳票テンプレートの内容を差し込めるようにする

4. **`config/company.php`**
   - 会社名・住所・電話番号・登録番号等を定義し、共通レイアウトから参照する

5. **`config/filesystems.php`**
   - 必要に応じてPDF保存用ディスク設定を追加（本タスクでは既存の`local`ディスクを利用する方針）

## 期待される失敗（Greenフェーズ実装前）

- `generateFromView()` / `buildStoragePath()` / `generateAndStore()` 呼び出し時に `Error: Call to undefined method`
- `PdfGenerationException` クラス参照時に `Error: Class "App\Exceptions\PdfGenerationException" does not exist`

これらはGreenフェーズでクラス・メソッドを実装することで解消される想定。
