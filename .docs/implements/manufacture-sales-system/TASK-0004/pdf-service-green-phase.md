# TASK-0004: PDFサービス基盤実装 - Greenフェーズ記録

## 実装方針

Redフェーズで失敗していた11テストを通すため、以下を実装した。

1. **`App\Exceptions\PdfGenerationException`**（新規）
   - `RuntimeException`を継承し、コンストラクタでビュー名と元例外（`Throwable`）を受け取る
   - メッセージはタスク詳細の例示文言「PDFの生成に失敗しました。しばらく経ってから再度お試しください。」に固定し、内部実装の詳細をユーザーに見せない

2. **`App\Providers\AppServiceProvider::register()`の拡張**
   - `Mpdf::class`をコンテナにバインドし、`config('mpdf')`（日本語フォント設定済み）を適用したインスタンスを生成するようにした
   - これにより`PdfService`は`app(Mpdf::class)`でMpdfを解決でき、テスト時には`$this->app->bind(Mpdf::class, fn () => $mock)`でモックに差し替え可能（タスク詳細「呼び出し方法を切り替えやすい設計」に対応）

3. **`App\Services\PdfService`の拡張**（既存スタブに3メソッドを追加）
   - `generateFromView(string $view, array $data = [], ?string $filename = null): string`
     - Bladeレンダリング→mPDF変換→`Output('', 'S')`でPDFバイナリを返す
     - 全体を`try/catch (Throwable $e)`で囲み、失敗時は`Log::error()`（テンプレート名・例外を記録、`$data`は個人情報配慮のため出力しない）→`PdfGenerationException`にラップしてスロー
   - `buildStoragePath(string $type, string $identifier, ?int $year = null): string`
     - `pdf/{帳票種別}/{年度}/{帳票種別}_{識別子}.pdf`形式の相対パスを返す
     - 年度は明示指定がなければ`now()->year`を採用する方針を実装し、コメントで明記（TC10対応、年度決定ロジックは要件に明記がなく実装時に方針を定めた部分）
   - `generateAndStore(string $view, array $data, string $type, string $identifier): string`
     - `generateFromView()`→`buildStoragePath()`→`Storage::disk('local')->put()`の順に実行し、保存先パスを返す
     - 保存失敗時も同様に`PdfGenerationException`にラップ・ログ記録する

4. **共通PDFレイアウト**
   - `resources/views/pdf/layouts/style.blade.php`: 罫線・フォントサイズ・余白等の共通CSS
   - `resources/views/pdf/layouts/base.blade.php`: mPDFの`<htmlpageheader>`/`<htmlpagefooter>`特殊タグでヘッダー（会社名・住所・電話番号・登録番号）とフッター（出力日時・ページ番号）を定義し、`@yield('content')`で各帳票テンプレートの内容を差し込む構成

5. **`config/company.php`**（新規）
   - 会社名・郵便番号・住所・電話番号・FAX・登録番号・ロゴパスを`env()`経由で定義し、共通レイアウトから参照できるようにした

## テスト実行結果

```
php artisan test --filter=PdfServiceTest
→ {"result":"passed","tests":9,"passed":9,"assertions":20}

php artisan test --filter=PdfServiceIntegrationTest
→ {"result":"passed","tests":2,"passed":2,"assertions":11}

php artisan test --filter=Pdf
→ {"result":"passed","tests":11,"passed":11,"assertions":31}
```

11テスト全て成功（既存のPDF関連テストへの影響なし）。

## 課題・改善点（Refactorフェーズで対応）

- `fromView()`/`download()`と`generateFromView()`の間でmPDF生成・HTMLレンダリングのロジックが一部重複している（リファクタで`generateFromView()`を内部的に共有するか整理を検討）
- `generateFromView()`/`generateAndStore()`の例外処理（ログ出力＋ラップ）が類似しており、共通化の余地がある
- `buildStoragePath()`の帳票種別文字列に対するバリデーション（想定外の種別が渡された場合の扱い）は未実装（現状はどんな文字列でもパスを生成する）
- 統合テストではStorageをfakeしているため、実ディスクへの書き込み権限・パス区切り文字（Windows/Linux）の差異は別途確認が必要
