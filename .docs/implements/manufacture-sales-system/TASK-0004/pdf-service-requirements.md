# TASK-0004: PDFサービス基盤実装 - 要件定義書

## 1. 機能の概要（EARS要件定義書・設計文書ベース）

- 🔵 見積書（REQ-032）・納品書（REQ-052）・請求書（REQ-061）の3帳票で共通利用するPDF生成基盤 `PdfService` を実装する機能
- 🔵 解決する問題: 各帳票機能（後続のTASK-0008/0010/0012）が個別にPDF生成ロジックを実装すると重複・不整合が生じるため、共通のラッパー・テンプレート・エラーハンドリング・保存ルールを一元化する
- 🔵 想定ユーザー: 営業・倉庫・経理担当者（帳票PDFをダウンロードする業務ユーザー）、および本基盤を利用する開発者（後続タスクの実装者）
- 🔵 システム内での位置づけ: `Services/PdfService.php`（mPDFラッパー）として `app/Services/` に配置し、Controller層から呼び出される共通基盤コンポーネント
- **参照したEARS要件**: REQ-032, REQ-052, REQ-061, EDGE-003
- **参照した設計文書**: architecture.md「ディレクトリ構造: Services/PdfService.php # mPDFラッパー」「resources/views/pdf/ # mPDF用テンプレート」, dataflow.md（PdfService呼び出しフロー）

## 2. 入力・出力の仕様

- 🔵 `generateFromView(string $view, array $data, ?string $filename = null): string`
  - 入力: Bladeビュー名（例: `pdf.test-template`）、ビューに渡すデータ配列、任意のファイル名
  - 出力: PDFバイナリ文字列（`%PDF-` で始まるバイト列）
- 🟡 `download(string $view, array $data, string $filename): Response`
  - 入力: ビュー名、データ配列、ダウンロード時のファイル名
  - 出力: `Content-Type: application/pdf` のダウンロードレスポンス
- 🟡 ファイル名・パス生成ヘルパー（例: `buildStoragePath(string $type, string $identifier): string`）
  - 入力: 帳票種別（`quotation`/`shipment`/`invoice`等）、識別子（採番番号・出荷ID・請求書番号等）
  - 出力: `pdf/{帳票種別}/{年度}/{命名規則ファイル名}.pdf` 形式の相対パス文字列（Storageディスク基準）
- 🔵 データフロー: Controller → `PdfService::generateFromView()`（Bladeビュー＋データ）→ mPDF（HTML→PDF変換、日本語フォント適用）→ PDFバイナリ → `Storage`へ保存 → 保存パスまたはバイナリを呼び出し元へ返却
- **参照したEARS要件**: REQ-032, REQ-052, REQ-061
- **参照した設計文書**: architecture.md「PdfService（mPDFラッパー）」, dataflow.md（PDF生成フロー）

## 3. 制約条件

- 🔵 日本語帳票が文字化けせず表示されること（mPDFの`fontDir`/`fontdata`に日本語フォントを設定。現状 `config/mpdf.php` で Meiryo を設定済み、本番ではIPAex Gothic等への切替を想定）
- 🟡 PDF生成失敗時はユーザーへ「再試行を促す」メッセージを伴う例外（`PdfGenerationException`）をスローし、ログ（`Log::error()`）に詳細を記録すること（EDGE-003）
- 🟡 例外メッセージ・ログには個人情報（顧客情報等）を含めないこと
- 🟡 生成したPDFは `storage/app/` 配下の規定ディレクトリ（`pdf/{帳票種別}/{年度}/`）に保存し、`Storage`ファサード経由で管理すること
- 🟡 帳票種別に依存しない汎用インターフェースとして設計し、帳票固有のレイアウト・データ整形は各帳票テンプレート側（後続タスク）に委ねる（責務分離）
- 🟡 将来的なキュー非同期化を見据え、呼び出し方法（同期/非同期）を切り替えやすい設計とする
- **参照したEARS要件**: EDGE-003, NFR-001（3秒以内のページ表示）, NFR-020（レスポンシブ）
- **参照した設計文書**: architecture.md（ディレクトリ構造・キュー非同期化の検討）, config/mpdf.php（既存の日本語フォント設定）

## 4. 想定される使用例

- 🔵 基本パターン: 後続タスク（見積書/納品書/請求書のPDF出力機能）が `PdfService::generateFromView()` または `download()` を呼び出し、共通レイアウトを適用した帳票PDFを生成・ダウンロードする
- 🔵 データフロー: 出荷完了時に `ShipmentService` が `PdfService` を呼び出して納品書PDFを生成（dataflow.md 機能2）、請求書発行時に `InvoiceService` が `PdfService` を呼び出して請求書PDFを生成（dataflow.md 機能3）
- 🟡 エッジケース: 存在しないビュー名や不正なデータが渡された場合、テンプレートのレンダリングに失敗し `PdfGenerationException` がスローされる
- 🟡 エラーケース: mPDFのフォント読み込み失敗・ファイル書き込み失敗等が発生した場合も同様に `PdfGenerationException` にラップしてスローし、画面にエラーメッセージ（再試行を促す内容）を表示する
- **参照したEARS要件**: EDGE-003
- **参照した設計文書**: dataflow.md（PdfService呼び出しシーケンス）

## 5. EARS要件・設計文書との対応関係

- **参照した機能要件**: REQ-032（見積PDFプレビュー・ダウンロード）, REQ-052（納品書PDF出力）, REQ-061（請求書PDFダウンロード）
- **参照した非機能要件**: NFR-001（3秒以内のページ表示）, NFR-020（モバイル対応）
- **参照したEdgeケース**: EDGE-003（PDF生成失敗時のエラーメッセージ・再試行促進）
- **参照した受け入れ基準**: 「PdfServiceがBladeビューからPDFバイナリを生成できる」「日本語が正しく表示される」「生成失敗時に例外がスローされる」「ファイル名・保存パスが命名規則どおりに生成される」（TASK-0004.md 単体テスト要件）
- **参照した設計文書**:
  - **アーキテクチャ**: architecture.md ディレクトリ構造（Services/PdfService.php, resources/views/pdf/）
  - **データフロー**: dataflow.md（PdfService呼び出しフロー、機能2・機能3）
  - **既存実装**: app/Services/PdfService.php（スタブ）, config/mpdf.php（mPDF設定済み）, resources/views/pdf/sample.blade.php（動作確認用テンプレート）

---

## 品質判定結果

✅ 高品質
- 要件の曖昧さ: なし（タスクファイル・既存スタブ実装・関連設計文書から明確に整理可能）
- 入出力定義: 完全（メソッドシグネチャ・PDFバイナリ形式・パス命名規則まで具体化）
- 制約条件: 明確（日本語フォント対応、エラーハンドリング、保存ディレクトリ構成）
- 実装可能性: 確実（mPDF・Blade・Storageファサードいずれも既存技術スタックの範囲内、PdfServiceスタブが既に存在）
- 信頼性レベル: 🔵 5件 / 🟡 9件（タスクファイルの🔵🟡比率と概ね整合）
