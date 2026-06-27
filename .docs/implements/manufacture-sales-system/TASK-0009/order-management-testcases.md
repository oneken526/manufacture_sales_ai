# 受注管理機能 テストケース一覧

**タスクID**: TASK-0009
**機能名**: 受注管理機能（Order Management）
**作成日**: 2026-06-27
**テストフレームワーク**: PHPUnit 11 / Laravel 13 TestCase（`php artisan test`）

---

## 開発言語・フレームワーク

🔵 *CLAUDE.md、note.md技術スタックより*

- **プログラミング言語**: PHP 8.4
- **テストフレームワーク**: PHPUnit 11（Laravelに同梱）、`RefreshDatabase`トレイト
- **テスト実行方法**: `php artisan test` または `php artisan test --filter=OrderManagementTest`
- **テスト配置**:
  - 統合テスト: `tests/Feature/Orders/OrderManagementTest.php`
  - 単体テスト(Service): `tests/Unit/Services/OrderServiceTest.php`

---

## 1. 単体テスト: OrderService

### TC-01: OrderService::cancel() が在庫引当解除・stock_movements記録・ステータス更新をアトミックに実行する
🔵 *REQ-043・TASK-0009.md「テストケース1」・dataflow.md「受注ステータス遷移」より*

- **テスト名**: `test_cancel_releases_reserved_quantity_and_records_stock_movement`
- **何をテストするか**: `confirmed`(=1)の受注をキャンセルすると、在庫引当解除・在庫変動記録・ステータス変更がDBトランザクション内でアトミックに実行されること
- **入力値**:
  - 受注: `status=CONFIRMED(1)`、明細に製品（quantity=5）
  - 製品: `stock_quantity=100`, `reserved_quantity=10`（うち5が当該受注分）
- **期待される結果**:
  - `products.reserved_quantity` が `10 → 5` に減算される
  - `stock_movements` に `reason=RESERVATION_RELEASE(2)`、`quantity_change=-5` のレコードが作成される
  - `sales_orders.status` が `CANCELLED(5)` に更新される
  - `sales_orders.cancelled_at` に現在時刻が記録される
- **確認ポイント**: `reserved_quantity` 減算・`stock_movements` 記録・ステータス更新がすべて単一トランザクション内で実行されること

---

### TC-02: OrderService::cancel() が confirmed から cancelled へ遷移させる（shipping_instructedも同様）
🔵 *REQ-043・dataflow.md「受注ステータス遷移（受注確定→キャンセル, 出荷指示済み→キャンセル）」より*

- **テスト名**: `test_cancel_works_for_both_confirmed_and_shipping_instructed_status`
- **何をテストするか**: `shipping_instructed`(=2)の受注もキャンセル可能であること
- **入力値**: 受注 `status=SHIPPING_INSTRUCTED(2)`
- **期待される結果**: `status` が `CANCELLED(5)` に更新され、在庫引当解除が実行される
- **確認ポイント**: ステータス遷移のガード処理が `confirmed` と `shipping_instructed` の両方を許可すること

---

### TC-03: OrderService::cancel() が shipped 以降の受注はキャンセルできない
🔵 *TASK-0009.md「テストケース2」・dataflow.md「受注ステータス遷移（shipped以降→cancelledの遷移は未定義）」より*

- **テスト名**: `test_cancel_throws_exception_when_order_is_already_shipped`
- **何をテストするか**: `shipped`(=3)以降のステータスにある受注をキャンセルしようとすると業務例外がスローされること
- **入力値**: 受注 `status=SHIPPED(3)`
- **期待される結果**: 業務例外（`\InvalidArgumentException` または専用例外）がスローされ、`reserved_quantity`・`stock_movements`・`status` のいずれにも変更が加わらない
- **確認ポイント**: DB変更が一切行われないこと（ロールバック保証）

---

### TC-04: OrderService::cancel() が invoiced 受注はキャンセルできない
🟡 *dataflow.md「受注ステータス遷移（invoiced以降はcancelledへの遷移は定義されていない）」から妥当な推測*

- **テスト名**: `test_cancel_throws_exception_when_order_is_invoiced`
- **入力値**: 受注 `status=INVOICED(4)`
- **期待される結果**: 業務例外がスローされ、DB変更なし

---

### TC-05: OrderService::cancel() 後も reserved_quantity が 0 以上を保つ
🔵 *TASK-0009.md完了条件「キャンセル処理後も reserved_quantity >= 0 の整合性が保たれること」より*

- **テスト名**: `test_cancel_ensures_reserved_quantity_does_not_go_negative`
- **何をテストするか**: 何らかの不整合で `reserved_quantity` が受注数量より少ない場合でも負値にならないこと
- **入力値**: 製品 `reserved_quantity=2`, 受注明細 `quantity=5`（引当数量が明細数量より少ない状態）
- **期待される結果**: 例外がスローされ、ロールバック後も `reserved_quantity=2` のまま保持される
- **確認ポイント**: 在庫整合性の最終防衛ラインとなる検証ロジックが動作すること

---

### TC-06: OrderService::issueShippingInstruction() が confirmed → shipping_instructed へ遷移させる
🔵 *REQ-041・TASK-0009.md「テストケース4」・database-schema.sqlのステータスコード表より*

- **テスト名**: `test_issue_shipping_instruction_changes_status_from_confirmed_to_shipping_instructed`
- **入力値**: 受注 `status=CONFIRMED(1)`
- **期待される結果**: `status` が `SHIPPING_INSTRUCTED(2)` に更新される
- **確認ポイント**: 他のフィールド（`reserved_quantity` 等）は変更されないこと

---

### TC-07: OrderService::issueShippingInstruction() が既に shipping_instructed の受注には例外をスローする
🔵 *TASK-0009.md実装詳細5「ステータス遷移として不正な状態からの発行操作はエラーとして扱う」より*

- **テスト名**: `test_issue_shipping_instruction_throws_exception_when_already_shipping_instructed`
- **入力値**: 受注 `status=SHIPPING_INSTRUCTED(2)`
- **期待される結果**: 業務例外がスローされ、ステータスは変更されない

---

### TC-08: OrderService::issueShippingInstruction() が cancelled 受注には例外をスローする
🔵 *dataflow.md「受注ステータス遷移（キャンセル済みからの出荷指示発行は遷移定義なし）」より*

- **テスト名**: `test_issue_shipping_instruction_throws_exception_for_cancelled_order`
- **入力値**: 受注 `status=CANCELLED(5)`
- **期待される結果**: 業務例外がスローされ、ステータスは変更されない

---

## 2. 統合テスト: OrderController（HTTPレベル）

### TC-09: sales ロールが受注一覧を表示できる（ステータスフィルタなし）
🔵 *api-endpoints.md「GET /orders: 権限=sales,accounting,admin」・REQ-040より*

- **テスト名**: `test_sales_user_can_view_orders_index`
- **入力値**: salesロールのユーザー、複数の受注データ（異なるステータス）
- **期待される結果**: `/orders` が 200 OK、受注一覧が表示される
- **確認ポイント**: ページネーション（50件/ページ）が機能していること

---

### TC-10: sales ロールがステータスフィルタで一覧を絞り込める
🟡 *TASK-0009.md完了条件「ステータスフィルタによる一覧絞り込みができること」から妥当な推測*

- **テスト名**: `test_sales_user_can_filter_orders_by_status`
- **入力値**: `?status=1`（confirmed）、受注データが confirmed と cancelled の両方存在
- **期待される結果**: confirmed の受注のみ表示され、cancelled の受注は表示されない

---

### TC-11: sales ロールが受注詳細を表示できる
🔵 *api-endpoints.md「GET /orders/{order}: 権限=sales,accounting,admin」より*

- **テスト名**: `test_sales_user_can_view_order_detail`
- **入力値**: salesロール、受注（明細・顧客情報あり）
- **期待される結果**: 200 OK、受注番号・顧客名・明細（製品名・数量・単価）・ステータスが表示される
- **確認ポイント**: 元の見積番号へのリンクが表示されること（quotation_idがある場合）

---

### TC-12: sales ロールが受注をキャンセルできる（在庫引当解除フロー）
🔵 *REQ-043・TASK-0009.md「統合テスト1」より*

- **テスト名**: `test_sales_user_can_cancel_order_and_reserved_quantity_is_released`
- **シナリオ**:
  1. `confirmed`(=1) の受注を準備（製品 `reserved_quantity=5`、明細 `quantity=5`）
  2. salesロールで `POST /orders/{order}/cancel` を実行
- **期待される結果**:
  - 302 リダイレクト（受注詳細画面）
  - `products.reserved_quantity` が `5 → 0` に減算される
  - `stock_movements` に `reason=RESERVATION_RELEASE(2)` が記録される
  - `sales_orders.status` が `CANCELLED(5)` に更新される
  - `sales_orders.cancelled_at` が記録される
  - セッションに成功フラッシュメッセージが含まれる

---

### TC-13: sales ロールが出荷指示を発行できる
🔵 *REQ-041・api-endpoints.md「POST /orders/{order}/shipping-instruction: 権限=sales,admin」より*

- **テスト名**: `test_sales_user_can_issue_shipping_instruction`
- **入力値**: salesロール、`confirmed`(=1) の受注
- **期待される結果**:
  - 302 リダイレクト（受注詳細画面）
  - `sales_orders.status` が `SHIPPING_INSTRUCTED(2)` に更新される
  - セッションに成功フラッシュメッセージが含まれる

---

### TC-14: admin ロールのみ受注を編集できる
🔵 *REQ-042・TASK-0009.md「テストケース3」・api-endpoints.md「PUT /orders/{order}: 権限=admin」より*

- **テスト名**: `test_only_admin_can_edit_confirmed_order`
- **シナリオ**:
  1. salesロールで `PUT /orders/{order}` を実行 → 403 Forbidden
  2. adminロールで `PUT /orders/{order}` を実行 → 302 リダイレクト（成功）
- **期待される結果**:
  - salesロールのリクエストは 403 Forbidden
  - adminロールのリクエストは受注内容が更新される

---

### TC-15: sales ロールが編集画面に直接アクセスしても 403 になる
🟡 *TASK-0009.md実装詳細3「admin以外のユーザーがアクセスした場合は403を返す」から妥当な推測*

- **テスト名**: `test_sales_user_gets_403_when_accessing_edit_page`
- **入力値**: salesロール、`GET /orders/{order}/edit` へのアクセス
- **期待される結果**: 403 Forbidden

---

### TC-16: shipped 以降の受注をキャンセルしようとするとエラーメッセージが表示される
🔵 *TASK-0009.md「統合テスト2」・dataflow.md「受注ステータス遷移」より*

- **テスト名**: `test_cannot_cancel_shipped_order_and_shows_error_message`
- **シナリオ**:
  1. `shipped`(=3) の受注を準備
  2. salesロールで `POST /orders/{order}/cancel` を実行
- **期待される結果**:
  - リダイレクト後にエラーメッセージが表示される（「出荷完了後はキャンセルできません」等）
  - `sales_orders.status` は `SHIPPED(3)` のまま変更されない
  - `stock_movements` にレコードが追加されない

---

### TC-17: 既に shipping_instructed の受注に出荷指示を再発行しようとするとエラーになる
🔵 *TASK-0009.md実装詳細5「すでに出荷指示済み...からの発行操作はエラーとして扱い、適切なメッセージを返す」より*

- **テスト名**: `test_cannot_issue_shipping_instruction_twice`
- **入力値**: `shipping_instructed`(=2) の受注
- **期待される結果**: エラーメッセージが表示され、ステータスは変更されない

---

### TC-18: warehouse ロールは受注管理画面にアクセスできない
🟡 *api-endpoints.md「GET /orders: 権限=sales,accounting,admin」から warehouse は含まれないため妥当な推測*

- **テスト名**: `test_warehouse_user_cannot_access_orders`
- **入力値**: warehouseロールのユーザー、`GET /orders`
- **期待される結果**: 403 Forbidden または リダイレクト

---

### TC-19: 受注統合フロー（確定→出荷指示→キャンセル不可の確認）
🔵 *TASK-0009.md「統合テスト2」・dataflow.md「受注ステータス遷移」より*

- **テスト名**: `test_full_order_flow_confirm_to_shipping_instruction_then_cancel_after_ship_fails`
- **シナリオ**:
  1. `confirmed` の受注を準備
  2. `POST /orders/{order}/shipping-instruction` → `SHIPPING_INSTRUCTED` に遷移
  3. 別途 `SHIPPED(3)` に手動更新
  4. `POST /orders/{order}/cancel` → エラー（キャンセル不可）
- **期待される結果**: ステップ2は成功、ステップ4はエラー（DB変更なし）

---

## 3. 境界値テスト

### TC-20: 明細が複数行ある受注のキャンセルで全明細分の reserved_quantity が正しく減算される
🔵 *REQ-043・TASK-0009.md実装詳細4「受注明細に紐づく各製品を lockForUpdate() で取得し、reserved_quantity を明細数量分だけ減算する」より*

- **テスト名**: `test_cancel_reduces_reserved_quantity_for_all_items`
- **入力値**:
  - 製品A: `reserved_quantity=10`, 明細 `quantity=3`
  - 製品B: `reserved_quantity=20`, 明細 `quantity=7`
- **期待される結果**:
  - 製品A: `reserved_quantity=10-3=7`
  - 製品B: `reserved_quantity=20-7=13`
  - `stock_movements` に2件分のRECORDが記録される

---

### TC-21: accounting ロールはキャンセルできない（権限確認）
🔵 *api-endpoints.md「POST /orders/{order}/cancel: 権限=sales,admin（accountingは含まれない）」より*

- **テスト名**: `test_accounting_user_cannot_cancel_order`
- **入力値**: accountingロールのユーザー、`confirmed` の受注
- **期待される結果**: 403 Forbidden（受注内容・在庫は変更されない）

---

### TC-22: ページネーション: 受注一覧は 50 件/ページで表示される
🔵 *NFR-021「50件/ページのページネーション」より*

- **テスト名**: `test_orders_index_paginates_50_per_page`
- **入力値**: 51件の受注データ
- **期待される結果**: 最初のページに50件、2ページ目に1件が表示される

---

## 4. テストケースと要件定義との対応関係

| テストケース | 対応要件 | 信頼性 |
|---|---|---|
| TC-01 | REQ-043（キャンセル+引当解除） | 🔵 |
| TC-02 | REQ-043, dataflow.md「受注ステータス遷移」 | 🔵 |
| TC-03 | TASK-0009.md テストケース2, dataflow.md | 🔵 |
| TC-04 | dataflow.md（遷移未定義） | 🟡 |
| TC-05 | TASK-0009.md完了条件（reserved_quantity>=0） | 🔵 |
| TC-06 | REQ-041, TASK-0009.md テストケース4 | 🔵 |
| TC-07 | TASK-0009.md実装詳細5（不正遷移ガード） | 🔵 |
| TC-08 | dataflow.md（遷移未定義） | 🔵 |
| TC-09 | api-endpoints.md, REQ-040 | 🔵 |
| TC-10 | TASK-0009.md完了条件（ステータスフィルタ） | 🟡 |
| TC-11 | api-endpoints.md, REQ-040 | 🔵 |
| TC-12 | REQ-043, 統合テスト1 | 🔵 |
| TC-13 | REQ-041, api-endpoints.md | 🔵 |
| TC-14 | REQ-042, TASK-0009.md テストケース3 | 🔵 |
| TC-15 | TASK-0009.md実装詳細3 | 🟡 |
| TC-16 | 統合テスト2, dataflow.md | 🔵 |
| TC-17 | TASK-0009.md実装詳細5 | 🔵 |
| TC-18 | api-endpoints.md（warehouse除外） | 🟡 |
| TC-19 | 統合テスト2, dataflow.md | 🔵 |
| TC-20 | REQ-043, 実装詳細4（複数明細） | 🔵 |
| TC-21 | api-endpoints.md（accounting除外） | 🔵 |
| TC-22 | NFR-021 | 🔵 |

---

## 5. テストファイル配置計画

| ファイル | テストケース |
|---|---|
| `tests/Unit/Services/OrderServiceTest.php` | TC-01〜TC-08 |
| `tests/Feature/Orders/OrderManagementTest.php` | TC-09〜TC-22 |

---

## 6. 品質評価

### 信頼性レベルサマリー

| 分類 | 🔵 青 | 🟡 黄 | 🔴 赤 | 計 |
|---|---|---|---|---|
| 単体テスト（TC-01〜08） | 7 | 1 | 0 | 8 |
| 統合テスト（TC-09〜19） | 8 | 3 | 0 | 11 |
| 境界値テスト（TC-20〜22） | 3 | 0 | 0 | 3 |
| **合計** | **18** | **4** | **0** | **22** |

**全体評価**: ✅ 高品質
- 正常系・異常系・境界値が網羅されている（22テストケース）
- 期待値定義: 各テストケースにDBアサーション・HTTPステータス・フラッシュメッセージを明記
- 技術選択: PHPUnit + Laravel TestCase（既存パターンと一致）
- 実装可能性: TASK-0008の`QuotationServiceTest`・`QuotationManagementTest`パターンを踏襲
- 🟡項目: ステータスフィルタUI・warehouseアクセス制限・invoicedキャンセル不可は設計文書から妥当に推測（根拠あり）
