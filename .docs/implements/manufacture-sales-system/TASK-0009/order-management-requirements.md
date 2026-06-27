# 受注管理機能 要件定義書

**タスクID**: TASK-0009
**機能名**: 受注管理機能（Order Management）
**フェーズ**: Phase 3 - 受注フロー機能
**作成日**: 2026-06-27

---

## 1. 機能の概要

### 目的・対象ユーザー
🔵 *REQ-040〜043、api-endpoints.md「受注管理」セクションより*

- **何をする機能か**: 受注の一覧表示（ステータスフィルタ）・詳細表示・編集・キャンセル・出荷指示発行を管理する
- **解決する問題**: 見積から転換された受注を一元管理し、在庫引当状態を正確に維持しながら受注ライフサイクル（確定→出荷指示→出荷→請求/キャンセル）を制御する
- **想定されるユーザー**:
  - `sales`ロール: 受注一覧・詳細閲覧、キャンセル、出荷指示発行
  - `accounting`ロール: 受注一覧・詳細閲覧のみ
  - `admin`ロール: 全操作（編集を含む）
- **システム内での位置づけ**: TASK-0008（見積管理）で作成された受注を管理し、TASK-0010（出荷管理）への橋渡しを担う。在庫の引当管理（reserved_quantity）の整合性を保証する中核機能

**参照したEARS要件**: REQ-040, REQ-041, REQ-042, REQ-043  
**参照した設計文書**: api-endpoints.md「受注管理」セクション（L114-125）

---

## 2. 入力・出力の仕様

### 2-1. 受注一覧（GET /orders）
🔵 *api-endpoints.md「受注管理」・REQ-040より*

| 項目 | 内容 |
|---|---|
| **入力** | クエリパラメータ: `status`（任意, OrderStatusコード1-6）、`customer_name`（任意, 部分一致検索）、`page`（任意, ページ番号） |
| **出力** | ページネーション付き受注一覧（50件/ページ、NFR-021）、ステータスバッジ色分け表示 |
| **権限** | sales, accounting, admin |

### 2-2. 受注詳細（GET /orders/{order}）
🔵 *api-endpoints.md・REQ-040より*

| 項目 | 内容 |
|---|---|
| **入力** | パスパラメータ: `order`（受注ID） |
| **出力** | 受注本体（order_number, status, confirmed_at, cancelled_at）、顧客情報、受注明細（product_name, quantity, unit_price, 小計）、元の見積へのリンク（quotation_idが存在する場合） |
| **権限** | sales, accounting, admin |

### 2-3. 受注編集（PUT /orders/{order}）
🟡 *REQ-042（要件定義書で🟡 推測）・api-endpoints.mdより*

| 項目 | 内容 |
|---|---|
| **入力** | フォームデータ: remarks（備考）等の変更可能フィールド |
| **出力** | 更新成功時リダイレクト（受注詳細）、バリデーションエラー時フォーム再表示 |
| **権限** | admin のみ（`OrderPolicy::update()`で制御） |
| **制約** | 受注確定後（status >= 1）はadminのみ編集可能 |

### 2-4. 受注キャンセル（POST /orders/{order}/cancel）
🔵 *REQ-043・dataflow.md「受注ステータス遷移」より*

| 項目 | 内容 |
|---|---|
| **入力** | キャンセル理由（任意テキスト）、受注ID |
| **出力** | キャンセル成功: 受注詳細へリダイレクト + 成功フラッシュメッセージ / 失敗（在庫不整合・不正ステータス）: エラーメッセージ表示 |
| **権限** | sales, admin |
| **キャンセル可能ステータス** | `confirmed`(=1)、`shipping_instructed`(=2) |
| **キャンセル不可ステータス** | `shipped`(=3)以降（出荷完了・請求済み・返品済み） |

**DBアトミック処理（`DB::transaction()`内）**:
1. `products.reserved_quantity` を明細数量分だけ減算（`lockForUpdate()`使用）
2. `stock_movements` に `reason=2`(RESERVATION_RELEASE) でレコード記録
3. `sales_orders.status` を `5`(cancelled) に更新、`cancelled_at` に現在時刻を記録

### 2-5. 出荷指示発行（POST /orders/{order}/shipping-instruction）
🔵 *REQ-041・dataflow.md「受注ステータス遷移」より*

| 項目 | 内容 |
|---|---|
| **入力** | 受注ID |
| **出力** | 発行成功: 受注詳細へリダイレクト + 成功フラッシュメッセージ / 失敗（不正ステータス）: エラーメッセージ表示 |
| **権限** | sales, admin |
| **ステータス遷移** | `confirmed`(=1) → `shipping_instructed`(=2) のみ有効 |

**参照したEARS要件**: REQ-040, REQ-041, REQ-042, REQ-043  
**参照した設計文書**: api-endpoints.md L120-124, dataflow.md「受注ステータス遷移」

---

## 3. 制約条件

### パフォーマンス要件
🔵 *NFR-021より*
- 受注一覧はページネーション50件/ページ

### セキュリティ・認可要件
🔵 *api-endpoints.md権限定義・REQ-042より*
- 受注一覧・詳細: `sales`, `accounting`, `admin` ロールのみ
- 受注編集: `admin` ロールのみ（`OrderPolicy` による認可制御 + UI制御の二重防御）
- キャンセル・出荷指示発行: `sales`, `admin` ロールのみ
- ルーティングは `middleware(['auth', 'verified', 'role:xxx'])` で制御

### データベース制約
🔵 *database-schema.sqlより*
- `sales_orders.status`: CHECK `BETWEEN 1 AND 6`
- `sales_orders.order_number`: UNIQUE（TASK-0008で採番済み）
- `sales_order_items.quantity`: CHECK `> 0`
- `products.reserved_quantity >= 0`（アプリ + DB制約で保証）

### アーキテクチャ制約
🔵 *architecture.md・Repository+Serviceパターンより*
- Controller → Service → Repository の3層構造
- `SalesOrderRepositoryInterface` + `EloquentSalesOrderRepository` の新規作成
- `OrderService` に業務ロジックを集約（cancel, issueShippingInstruction）
- `AppServiceProvider` に `SalesOrderRepositoryInterface → EloquentSalesOrderRepository` のDIバインディング登録

### トランザクション・整合性制約
🔵 *dataflow.md「データ整合性の保証」・REQ-043より*
- キャンセル処理は必ず `DB::transaction()` 内で実行
- 在庫操作対象の製品には `lockForUpdate()` による悲観的ロックを適用
- `reserved_quantity` 減算後に負値にならないことをアプリレベルで検証し、不整合時は例外スローしロールバック

### ステータス遷移制約
🔵 *dataflow.md「受注ステータス遷移」ステートマシン図より*
```
受注確定(1) → 出荷指示済み(2): 出荷指示発行（REQ-041）
受注確定(1) → キャンセル(5): 受注キャンセル（REQ-043）
出荷指示済み(2) → キャンセル(5): 受注キャンセル（REQ-043）
出荷指示済み(2) → 出荷完了(3): 出荷完了登録（TASK-0010）
```
- `shipped`(=3)以降はキャンセル不可（ガード処理必須）
- 不正なステータス遷移は業務例外をスローし、DB変更なし

**参照したEARS要件**: NFR-021, REQ-042, REQ-043  
**参照した設計文書**: architecture.md, database-schema.sql, dataflow.md L328-342

---

## 4. 想定される使用例

### 4-1. 基本フロー: 受注確認と出荷指示発行
🔵 *REQ-041・dataflow.md「受注ステータス遷移」より*

1. salesユーザーが `/orders` で受注一覧を確認（ステータスフィルタ: confirmed）
2. 対象受注の詳細を確認（明細・顧客情報・引当在庫状況）
3. 「出荷指示発行」ボタンを押下
4. ステータスが `confirmed`(1) → `shipping_instructed`(2) に更新
5. 成功メッセージが表示され、TASK-0010の出荷指示一覧に表示されるようになる

### 4-2. 受注キャンセルフロー（在庫引当解除）
🔵 *REQ-043・dataflow.md「受注ステータス遷移」より*

1. salesユーザーが受注詳細画面から「キャンセル」ボタンを押下
2. 確認ダイアログ（「キャンセルすると在庫引当が解除されます」）を表示
3. 確認後、`OrderService::cancel()` がDBトランザクション内で実行:
   - 製品に `lockForUpdate()`
   - `products.reserved_quantity` を明細数量分減算
   - `stock_movements` に `reason=2`(RESERVATION_RELEASE) で記録
   - `sales_orders.status=5`(cancelled)、`cancelled_at=now()` に更新
4. 成功メッセージ表示、ステータスバッジが「キャンセル」（灰色）に変わる

### 4-3. adminによる受注編集フロー
🟡 *REQ-042（要件定義書で🟡）より*

1. adminユーザーが受注詳細画面で「編集」ボタンを確認（salesユーザーには非表示）
2. 編集フォームで修正可能フィールドを変更
3. `OrderPolicy::update()` によりadminであることを認可チェック
4. 保存成功後、受注詳細へリダイレクト

### 4-4. エッジケース: 出荷完了後のキャンセル拒否
🔵 *dataflow.md「受注ステータス遷移」（shipped以降→cancelledの遷移は未定義）より*

- **Given**: `shipped`(=3)以降のステータスにある受注
- **When**: キャンセル操作を実行
- **Then**: 業務例外がスローされ、DB変更なし。「出荷完了後はキャンセルできません」エラーメッセージを表示

### 4-5. エッジケース: admin以外による編集拒否（REQ-042）
🟡 *REQ-042（要件定義書で🟡）・OrderPolicyより*

- **Given**: salesロールのユーザーが受注編集エンドポイント（PUT /orders/{order}）を直接呼び出す
- **When**: リクエスト送信
- **Then**: `OrderPolicy::update()` が拒否し、403レスポンスを返す。受注内容は変更されない

**参照したEARS要件**: REQ-041, REQ-042, REQ-043, EDGE-001  
**参照した設計文書**: dataflow.md L328-342

---

## 5. EARS要件・設計文書との対応関係

| 要件ID | 内容 | 実装箇所 |
|---|---|---|
| REQ-040 | 受注確定後、システムは在庫数を引き当て（引き落とし予約）しなければならない | TASK-0008で実装済み（QuotationService::confirmToOrder）|
| REQ-041 | システムは受注から出荷指示を発行できなければならない | OrderService::issueShippingInstruction() |
| REQ-042 | 受注確定後はシステム管理者のみが内容編集できなければならない（🟡） | OrderPolicy::update() |
| REQ-043 | システムはキャンセル処理ができ、キャンセル時は引き当て在庫を戻さなければならない | OrderService::cancel() |
| NFR-021 | ページネーション50件/ページ | OrderController::index() |

### 参照した設計文書まとめ

| 設計文書 | 参照箇所 |
|---|---|
| **データフロー** | dataflow.md「受注ステータス遷移」（L328-342）、「データ整合性の保証」（L363-365） |
| **API仕様** | api-endpoints.md「受注管理」（L114-125）、権限定義（L40-50） |
| **データベース** | sales_orders（status CHECK, idx_status）、sales_order_items（quantity CHECK）、products（reserved_quantity）、stock_movements（reason=2） |
| **アーキテクチャ** | architecture.md「Repository+Serviceパターン」、認可制御セクション |

---

## 6. 実装ファイル一覧

| ファイル | 状態 | 概要 |
|---|---|---|
| `app/Models/SalesOrder.php` | 既存（拡充） | scopeStatus()等のスコープ追加 |
| `app/Models/SalesOrderItem.php` | 既存 | 変更なし |
| `app/Repositories/Contracts/SalesOrderRepositoryInterface.php` | **新規** | リポジトリインターフェース |
| `app/Repositories/Eloquent/EloquentSalesOrderRepository.php` | **新規** | Eloquent実装 |
| `app/Services/OrderService.php` | **新規** | cancel(), issueShippingInstruction()等 |
| `app/Policies/OrderPolicy.php` | **新規** | update()でadmin判定 |
| `app/Http/Controllers/OrderController.php` | **新規** | index/show/edit/update/cancel/issueShippingInstruction |
| `app/Http/Requests/UpdateSalesOrderRequest.php` | **新規** | 受注編集バリデーション |
| `database/factories/SalesOrderItemFactory.php` | **新規** | テスト用ファクトリ |
| `resources/views/orders/index.blade.php` | **新規** | 受注一覧（ステータスフィルタ・バッジ色分け） |
| `resources/views/orders/show.blade.php` | **新規** | 受注詳細（編集・キャンセル・出荷指示ボタン） |
| `resources/views/orders/edit.blade.php` | **新規** | 受注編集フォーム（adminのみ表示） |
| `routes/web.php` | 既存（追加） | 受注管理ルート追加 |
| `app/Providers/AppServiceProvider.php` | 既存（追加） | SalesOrderRepositoryInterfaceバインド登録 |

---

## 7. 品質評価

### 信頼性レベルサマリー

| カテゴリ | 🔵 青 | 🟡 黄 | 🔴 赤 | 評価 |
|---|---|---|---|---|
| 機能概要 | 3 | 0 | 0 | ✅ 高品質 |
| 入出力仕様 | 4 | 1 | 0 | ✅ 高品質 |
| 制約条件 | 5 | 0 | 0 | ✅ 高品質 |
| 使用例・エッジケース | 3 | 2 | 0 | ✅ 高品質 |

**全体評価**: ✅ 高品質
- 要件の曖昧さ: ほぼなし（REQ-042の「確定後」の範囲のみ🟡）
- 入出力定義: 完全
- 制約条件: 明確（トランザクション・ロック・ステータス遷移すべて定義済み）
- 実装可能性: 確実（TASK-0008の実装パターンを踏襲）
- 🟡項目の補足: REQ-042（admin編集制限）は要件定義書自体が🟡推測扱い。本タスクでは「status >= 1の受注はadminのみ編集可能」と解釈して実装する
