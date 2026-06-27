# TASK-0008: 見積管理機能（作成・PDF・受注転換） 要件定義書

## 1. 機能の概要（EARS要件定義書・設計文書ベース）

- 🔵 営業担当者が顧客・製品明細を選択して見積書を作成し、PDFのプレビュー・ダウンロードを行い、見積から受注への転換（在庫引当・受注レコード作成）までを一気通貫で行う機能である
- 🔵 解決する問題: 見積作成から受注確定までの業務フローをシステム化し、見積番号の重複採番や在庫引当漏れ・在庫超過受注といった不整合を防止する（As a 営業担当者 / So that 受注処理を正確かつ迅速に行える）
- 🔵 想定ユーザー: 営業担当者（sales）・管理者（admin）
- 🔵 システム内での位置づけ: `architecture.md`のController→Service→Repositoryパターンに従い、`QuotationController`→`QuotationService`→`QuotationRepository`（+`ProductRepository`/`SalesOrderRepository`）で構成。`PdfService`（TASK-0004）を利用してPDF生成を行う
- **参照したEARS要件**: REQ-030, REQ-031, REQ-032, REQ-033, REQ-040, REQ-041
- **参照した設計文書**: architecture.md（Repository+Serviceパターン）、dataflow.md「機能1: 見積作成→受注確定（在庫引き当て）」

## 2. 入力・出力の仕様（EARS機能要件・データベーススキーマベース）

- 🔵 **見積作成の入力**: 顧客ID（`customer_id`）、明細行（`product_id`, `quantity`, `unit_price`の配列。1件以上必須）、有効期限（`expires_at`、日付・任意）、備考（`remarks`、任意）
  - 参照: `quotations`テーブル（`quotation_number varchar(30) unique`, `customer_id FK`, `status tinyint default=1`, `remarks text nullable`, `expires_at date nullable`, `created_by FK users`）、`quotation_items`テーブル（`quotation_id FK cascade`, `product_id FK`, `quantity integer (CHECK > 0)`, `unit_price bigint`）
- 🔵 **見積作成の出力**: 採番された見積番号（`QUO-{年度}-{連番4桁}`形式、例: `QUO-2026-0001`）を持つ`Quotation`レコードと`QuotationItem`群。一覧画面へのリダイレクトと成功メッセージ
- 🔵 **PDF出力**: `QuotationController::pdf()`が`PdfService::download()`または`generateFromView()`を介し、`resources/views/pdf/quotations/show.blade.php`をレンダリングしたPDFバイナリ（プレビュー: インラインストリーム / ダウンロード: `attachment`レスポンス）を返す
- 🔵 **受注確定の入力**: 見積ID（ルートパラメータ`{quotation}`）。`POST /quotations/{quotation}/confirm`
- 🔵 **受注確定の出力（成功時）**: `products.reserved_quantity`が明細数量分加算、`stock_movements`に`reason=1`(reservation)レコード作成、`sales_orders`(`status=1`confirmed, `quotation_id`に元見積ID)・`sales_order_items`レコード作成、`quotations.status`が`2`(converted)へ更新。成功メッセージ「受注を確定しました」を返却
- 🔵 **受注確定の出力（在庫不足時）**: `InsufficientStockException`がスローされ、不足製品ID・要求数量・利用可能数量を含む警告メッセージがフラッシュされ、いかなるDB変更も行われない
- 🔵 **データフロー**: `見積作成フォーム → StoreQuotationRequestでバリデーション → QuotationService::create() → DocumentNumberGenerator（document_sequencesをlockForUpdateで採番）→ Quotation/QuotationItem保存`、`見積詳細画面 → 受注確定ボタン → QuotationController::confirm() → QuotationService::confirmToOrder()（DB::transaction内で在庫チェック・引当・受注作成・ステータス更新）`
- **参照したEARS要件**: REQ-030, REQ-031, REQ-032, REQ-040
- **参照した設計文書**: database-schema.sql（quotations, quotation_items, sales_orders, sales_order_items, stock_movements, document_sequences）、dataflow.md「機能1」シーケンス図、api-endpoints.md「見積管理」セクション

## 3. 制約条件（EARS非機能要件・アーキテクチャ設計ベース）

- 🟡 **パフォーマンス要件**: 一覧画面はページネーション1ページ50件（NFR-021）。PDF生成・DBトランザクションを伴う処理は3秒以内のページ表示（NFR-001）を考慮し、処理中はローディング表示を行う
- 🔵 **セキュリティ・権限要件**: ルートは`middleware(['auth', 'verified', 'role:sales,admin'])`でsales/adminロールに限定する（api-endpoints.mdの権限定義: `/quotations`系は全てsales, adminのみ）
- 🔵 **アーキテクチャ制約**: Controller→Service→Repositoryの3層構造を遵守し、Service層はRepositoryインターフェースに依存する（DI、`AppServiceProvider`でバインド登録）。業務データはDTO（`QuotationData`/`QuotationItemData`）経由で受け渡す
- 🔵 **データベース制約**:
  - `quotation_items.quantity`・`sales_order_items.quantity`はCHECK制約 `> 0`（MySQL）
  - `document_sequences`は`UNIQUE(document_type, fiscal_year)`、`document_type BETWEEN 1 AND 3`（QUOTATION=1, ORDER=2, INVOICE=3）
  - `quotations.status`: 1=draft, 2=converted, 3=cancelled, 4=expired（`QuotationStatus` enum既存）
  - `sales_orders.status`: 1=confirmed 等（`OrderStatus` enum既存、CHECK `BETWEEN 1 AND 6`）
  - `stock_movements.reason=1`は`StockMovementReason::RESERVATION`（既存enum）
  - 利用可能在庫 = `stock_quantity - reserved_quantity` がマイナスにならないことをアプリ・DB両面で保証する
- 🔵 **トランザクション制約**: `confirmToOrder()`は`DB::transaction()`内で対象製品に`lockForUpdate()`を適用し、在庫チェック→引当→stock_movements記録→受注作成→ステータス更新を単一トランザクションでアトミックに実行する。失敗時は全件ロールバック
- 🔵 **API制約**: `GET /quotations`, `GET /quotations/create`, `POST /quotations`, `GET /quotations/{quotation}`, `GET /quotations/{quotation}/pdf`, `POST /quotations/{quotation}/confirm`、内部API `POST /api/internal/quotations/calculate`（金額再計算）
- **参照したEARS要件**: NFR-001, NFR-021, REQ-031, REQ-040
- **参照した設計文書**: architecture.md, database-schema.sql（quotations, quotation_items, sales_orders, sales_order_items, document_sequences, stock_movementsの制約定義）, api-endpoints.md「見積管理」セクション

## 4. 想定される使用例（EARSEdgeケース・データフローベース）

- 🔵 **基本パターン**: 営業担当者が見積作成画面で顧客・複数の製品明細・有効期限を入力して保存 → 見積番号が自動採番される → 見積詳細画面でPDFプレビューを確認 → 「受注確定」を実行 → 在庫引当・受注作成が行われ成功メッセージが表示される（dataflow.md「機能1」、REQ-030〜032, REQ-040）
- 🔵 **エッジケース1（EDGE-001 在庫不足）**: 見積明細のいずれかの製品の利用可能在庫が明細数量未満の場合、`InsufficientStockException`がスローされ、確定処理が中止・ロールバックされ、警告メッセージ（不足製品名・要求数量・利用可能数量）が表示される
- 🟡 **エッジケース2（EDGE-011 明細0件）**: 見積に明細が1件も登録されていない場合、見積詳細画面の「受注確定」ボタンが非活性化され、保存時にもバリデーションエラーとなる
- 🟡 **エッジケース3（見積有効期限切れ、REQ-033）**: `expires_at`を過ぎた`status=draft`の見積は`status=4`(expired)へ自動判定され、受注確定操作が不可となる
- 🔵 **エラーケース（採番の同時実行）**: 同一年度の見積保存が同時に複数発生した場合、`document_sequences`への`lockForUpdate()`により採番が直列化され、`QUO-{年度}-{連番}`が重複しない
- **参照したEARS要件**: EDGE-001, EDGE-011, REQ-033
- **参照した設計文書**: dataflow.md「機能1: 見積作成→受注確定（在庫引き当て）」シーケンス図のalt分岐（在庫不足時）

## 5. EARS要件・設計文書との対応関係

- **参照した機能要件**: REQ-030（見積作成）, REQ-031（見積→受注ステータス変更）, REQ-032（見積PDFプレビュー・ダウンロード）, REQ-033（見積有効期限）, REQ-040（受注確定後の在庫引当）, REQ-041（受注からの出荷指示発行 ※後続TASK-0009で本格実装）
- **参照した非機能要件**: NFR-001（3秒以内表示）, NFR-021（ページネーション50件）
- **参照したEdgeケース**: EDGE-001（在庫不足時の警告・処理中止）, EDGE-011（明細0件時の確定ボタン非活性化）
- **参照した受け入れ基準**: TASK-0008.md「完了条件」全10項目（モデル/採番/Controller/PDF/confirmToOrder/在庫不足ハンドリング/有効期限/動的UI/バリデーション/テスト）
- **参照した設計文書**:
  - **アーキテクチャ**: architecture.md（Controller→Service→Repositoryパターン、DTO設計）
  - **データフロー**: dataflow.md「機能1: 見積作成→受注確定（在庫引き当て）」シーケンス図
  - **データベース**: database-schema.sql（quotations, quotation_items, sales_orders, sales_order_items, stock_movements, document_sequences）。実体は database/migrations/2026_06_07_000040〜000070, 000120 の各マイグレーションファイル
  - **API仕様**: api-endpoints.md「見積管理」セクション（L101-112）、「見積・受注の動的明細操作」セクション（L203-209）
  - **既存実装パターン**: app/Services/ProductService.php・app/Repositories/Eloquent/ProductRepository.php（`adjustStock()`のトランザクション+lockForUpdateパターン）、app/Models/{Customer,SalesOrder,StockMovement}.php、app/Enums/{QuotationStatus,OrderStatus,DocumentType,StockMovementReason}.php（既存enum流用）

---

## 品質判定

- **要件の曖昧さ**: なし — 完了条件・実装詳細・テスト要件がTASK-0008.mdに明記され、関連する全テーブル定義・enumが既存実装として確認できた
- **入出力定義**: 完全 — DBスキーマ（マイグレーション）・既存enum・PdfServiceのI/Fまで実体を確認済み
- **制約条件**: 明確 — トランザクション・ロック・CHECK制約・権限ミドルウェアの方針はTASK-0005/0006の実装パターンと完全に整合
- **実装可能性**: 確実 — Repository+Service+Controller+DTOの3層構造、`adjustStock()`を踏襲した`confirmToOrder()`実装、`PdfService`を活用したPDF生成のいずれも先行タスク（TASK-0005, 0006, 0004）の実装パターンを流用できる
- **信頼性レベル**: 🔵が大多数（コア機能の在庫引当・受注作成・採番はすべて要件定義書・DBスキーマに明記）。🟡は見積有効期限の自動判定・動的UI操作（REQ-033・行追加削除UXが要件定義書でも🟡指定）に限定される

### 全体評価
✅ 高品質 — 曖昧さなし、入出力定義完全、制約条件明確、実装可能性確実、信頼性レベルは🔵中心。次フェーズ（テストケース洗い出し）に進める状態。
