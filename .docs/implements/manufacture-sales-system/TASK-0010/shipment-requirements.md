# TASK-0010 出荷管理機能 要件定義書

## 1. 機能の概要

### 🔵 目的
出荷指示済みの受注に対して、出荷完了登録（在庫実減算）・納品書PDF出力・返品登録を行う機能。
倉庫・出荷担当者（warehouseロール）が主に操作し、請求書操作には関与しない。

### 🔵 想定ユーザー
- **warehouse**: 出荷指示一覧参照・出荷完了登録・納品書ダウンロード・返品登録
- **admin**: 上記すべて + 全体管理
- **sales**: 納品書ダウンロードのみ（REQ-052）

### 🔵 システム内での位置づけ
受注ステータス遷移の「出荷指示済み(2) → 出荷完了(3)」および「出荷完了(3) → 返品済み(6)」を担うレイヤー。
在庫整合性（stock_quantity/reserved_quantity）を維持しつつ、後続の請求管理（TASK-0012）に連携する。

**参照したEARS要件**: REQ-050, REQ-051, REQ-052, REQ-053
**参照した設計文書**: architecture.md（ShipmentService位置づけ）, dataflow.md（機能2）

---

## 2. 入力・出力の仕様

### 🔵 出荷完了登録（POST /shipments/{order}/complete）
- **入力**: sales_order_id（URLパラメータ）
- **前提条件**: `sales_orders.status = SHIPPING_INSTRUCTED(2)`
- **出力**: 成功時→在庫減算済み・shipments レコード作成・status=SHIPPED(3)・納品書PDFダウンロードリンク
- **エラー時**: バリデーションエラーまたは業務例外メッセージ表示

### 🔵 返品登録（POST /shipments/{shipment}/return）
- **入力**: return_reason（必須テキスト）
- **前提条件**: `sales_orders.status = SHIPPED(3)`
- **出力**: 成功時→在庫加算・status=RETURNED(6)・returned_at/return_reason記録

### 🔵 shipments テーブル（database-schema.sqlより）
| カラム | 型 | 説明 |
|---|---|---|
| id | BIGINT PK | |
| sales_order_id | BIGINT FK | 受注ID |
| shipped_at | TIMESTAMP NULL | 出荷完了日時 |
| delivery_note_path | VARCHAR(500) NULL | 納品書PDFパス |
| returned_at | TIMESTAMP NULL | 返品日時 |
| return_reason | TEXT NULL | 返品理由 |
| shipped_by | BIGINT FK NULL | 出荷操作者 |

**参照したEARS要件**: REQ-051, REQ-052, REQ-053
**参照した設計文書**: database-schema.sql（shipmentsテーブル）, api-endpoints.md

---

## 3. 制約条件

### 🔵 トランザクション要件
- 出荷完了処理（stock_quantity/reserved_quantity減算 + stock_movements記録 + status更新）は`DB::transaction()`内でアトミックに実行
- 対象製品には`lockForUpdate()`による悲観的ロックを適用（並行操作防止）
- PDF生成はトランザクション外（コミット後）で実行（PDF失敗時に在庫減算をロールバックしない）

### 🔵 在庫整合性
- 減算後に`stock_quantity >= 0`かつ`reserved_quantity >= 0`をアプリ層で事前検証
- 不整合時は例外をスローしてロールバック

### 🔵 アクセス制御（REQ-003）
- 出荷指示一覧・出荷完了・返品登録: `role:warehouse,admin`
- 納品書ダウンロード: `role:warehouse,sales,admin`
- 請求管理エンドポイント（/invoices等）へのwarehouseアクセスは禁止

### 🔵 stock_movements記録（REQ-072）
- 出荷完了時: `reason=SHIPMENT(3)`, `quantity_change`=負値（減算量）
- 返品時: `reason=RETURN_RECEIVED(4)`, `quantity_change`=正値（加算量）
- `related_order_id`, `operated_by`, `created_at`を必ず記録

**参照したEARS要件**: REQ-003, REQ-051, REQ-072
**参照した設計文書**: dataflow.md（データ整合性の保証）

---

## 4. 想定される使用例

### 🔵 基本フロー（出荷完了）
1. warehouseユーザーが`GET /shipments`（出荷指示一覧）を表示
2. `sales_orders.status = SHIPPING_INSTRUCTED(2)`の受注一覧が表示される
3. 対象受注の「出荷完了」ボタンをクリック（確認ダイアログ表示）
4. `POST /shipments/{order}/complete`がPOST送信される
5. ShipmentService::complete()がトランザクション内で以下を実行：
   - 各明細製品をlockForUpdate()で取得
   - stock_quantity -= quantity, reserved_quantity -= quantity
   - stock_movements INSERT（reason=3）
   - shipments INSERT（shipped_at=now(), shipped_by=auth_user）
   - sales_orders UPDATE（status=3）
6. コミット後にPDF生成→delivery_note_path保存
7. 成功画面（ダウンロードリンク付き）を表示

### 🔵 基本フロー（返品）
1. warehouseユーザーが出荷完了済み受注詳細から「返品登録」を選択
2. return_reason（必須）を入力して送信
3. ShipmentService::processReturn()がトランザクション内で以下を実行：
   - 各明細製品のstock_quantity += quantity
   - stock_movements INSERT（reason=4）
   - sales_orders UPDATE（status=6, returned_at=now(), return_reason=入力値）
4. 返品完了メッセージを表示

### 🟡 エッジケース
- 出荷指示未発行（status≠2）の受注に対するcomplete()→業務例外スロー
- stock_quantityが減算量を下回る状態（データ不整合）→例外スロー+ロールバック
- PDF生成失敗→エラーログ記録・画面にメッセージ表示、在庫減算はロールバックしない

**参照したEARS要件**: REQ-050〜053
**参照した設計文書**: dataflow.md（機能2シーケンス図、受注ステータス遷移）

---

## 5. EARS要件・設計文書との対応関係

- **参照した機能要件**: REQ-050, REQ-051, REQ-052, REQ-053, REQ-072
- **参照した非機能要件**: REQ-003, NFR-020, NFR-021
- **参照した設計文書**:
  - **アーキテクチャ**: architecture.md（ShipmentService, ShipmentRepository構成）
  - **データフロー**: dataflow.md（機能2: 出荷完了登録シーケンス図、受注ステータス遷移）
  - **データベース**: database-schema.sql（shipmentsテーブル, stock_movements.reasonコード表）
  - **API仕様**: api-endpoints.md（出荷管理セクション, 権限テーブル）

---

## 完了条件チェックリスト

- [ ] Shipmentモデル・ShipmentRepository（Interface + Eloquent）・ShipmentServiceが実装されている
- [ ] ShipmentService::complete()が在庫実減算・stock_movements記録・status=SHIPPED・shipped_atをトランザクション内でアトミックに実行する
- [ ] 納品書PDF（PdfService使用）が生成されdelivery_note_pathに保存される（REQ-052）
- [ ] ShipmentService::processReturn()が在庫加算・stock_movements記録・status=RETURNED・returned_at/return_reasonを記録する
- [ ] warehouse権限での出荷管理画面アクセスができ、請求書操作が不可である（REQ-003）
- [ ] バリデーションエラー・権限エラーが適切に表示される
- [ ] 単体テスト・統合テストがすべて成功する
