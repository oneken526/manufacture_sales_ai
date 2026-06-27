# TASK-0010 出荷管理機能 テストケース一覧

## 単体テスト（Unit Tests）

### TC-U01: ShipmentService::complete()が在庫を減算しstock_movementsに記録する 🔵
- **Given**: `SHIPPING_INSTRUCTED(2)`の受注、製品 stock_quantity=100, reserved_quantity=20、明細quantity=10
- **When**: `ShipmentService::complete($order, $userId)`を実行
- **Then**:
  - 製品のstock_quantity=90、reserved_quantity=10に更新
  - stock_movementsに reason=SHIPMENT(3), quantity_change=-10, related_order_id が記録される
  - shipmentsレコードが作成され、shipped_at・shipped_byが設定される
  - sales_orders.statusが SHIPPED(3) に更新される

### TC-U02: 返品処理が在庫を正しく加算しstock_movementsに記録する 🔵
- **Given**: `SHIPPED(3)`の受注、shipmentレコードあり、製品 stock_quantity=90
- **When**: `ShipmentService::processReturn($shipment, '製品不良のため', $userId)`を実行
- **Then**:
  - 製品のstock_quantityが返品数量分だけ加算される
  - stock_movementsに reason=RETURN_RECEIVED(4), quantity_change=+N が記録される
  - sales_orders.statusが RETURNED(6) に更新される
  - returned_at・return_reasonが記録される

### TC-U03: 出荷指示未発行の受注はcomplete()を拒否する 🟡
- **Given**: `CONFIRMED(1)`ステータスの受注
- **When**: `ShipmentService::complete($order, $userId)`を実行
- **Then**: 業務例外（InvalidArgumentException等）がスローされる
  - 在庫・stock_movements・受注ステータスのいずれも変更されない

### TC-U04: 在庫減算後にstock_quantity/reserved_quantityが負値にならないことを保証する 🔵
- **Given**: 製品 stock_quantity=5, reserved_quantity=5、明細quantity=10（不整合状態）
- **When**: `ShipmentService::complete($order, $userId)`を実行
- **Then**: 整合性エラー例外がスローされトランザクションがロールバックされる
  - 在庫数が負値にならない

---

## 統合テスト（Feature Tests）

### TC-F01: 出荷完了登録フロー（出荷指示一覧→出荷完了→在庫減算確認） 🔵
- **シナリオ**:
  1. warehouseユーザーでログイン
  2. `GET /shipments` → 出荷指示一覧が表示される（status=200）
  3. `POST /shipments/{order}/complete` → 成功レスポンス（リダイレクト）
  4. DBで stock_quantity/reserved_quantity が減算済み、stock_movements記録あり、status=3を確認
- **期待結果**: 一連フローが正常完了

### TC-F02: 返品登録フロー（出荷完了済み→返品→在庫加算確認） 🔵
- **シナリオ**:
  1. warehouseユーザーでログイン
  2. `POST /shipments/{shipment}/return`（return_reason='製品不良のため'） → 成功レスポンス
  3. DBで stock_quantity加算済み、stock_movements記録あり、status=6確認
- **期待結果**: 返品処理が正常完了

### TC-F03: warehouse権限での請求書エンドポイントへのアクセス拒否 🔵
- **Given**: warehouseロールのユーザー
- **When**: `GET /invoices` にアクセス
- **Then**: 403 Forbiddenが返却される

### TC-F04: 未認証ユーザーの出荷管理アクセス拒否 🔵
- **Given**: 未ログイン状態
- **When**: `GET /shipments` にアクセス
- **Then**: ログイン画面にリダイレクト（302）
