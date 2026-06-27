# TASK-0006: 製品マスタ管理機能 - 要件整理

## 1. 機能の概要 🔵
- 製品マスタ（`products`テーブル）のCRUD（一覧・登録・編集・検索）と、在庫アラート表示・在庫の手動調整機能を提供する
- 利用者: 全役割（一覧閲覧）、admin（登録・編集）、warehouse・admin（在庫調整）
- システム内の位置づけ: 見積作成（TASK-0008）・受注確定（TASK-0011）・売上レポート（TASK-0015）から参照される基幹マスタ
- 参照したEARS要件: REQ-020, REQ-021, REQ-022, REQ-023, REQ-072
- 参照した設計文書: architecture.md（Repository+Serviceパターン）

## 2. 入力・出力の仕様 🔵
- `ProductData` DTO（id, productCode, productName, unitPrice, unit, stockQuantity, reservedQuantity, alertThreshold）
  - `availableQuantity()` = stockQuantity - reservedQuantity
  - `isLowStock()` = stockQuantity < alertThreshold（🟡 推測: data-types.phpにメソッド名の明記はないが、availableQuantity()と対になる自然な拡張）
- 在庫調整入力: `quantity_change`（正負いずれも可、0は不可）, `memo`（任意）
- 出力: 製品一覧（ページネーション50件）、調整後在庫数
- 参照したEARS要件: REQ-020〜REQ-023
- 参照した設計文書: data-types.php（ProductData定義）, database-schema.sql（productsテーブル）

## 3. 制約条件 🔵
- ページネーション1ページ50件（NFR-021）
- 検索はEloquentクエリビルダのLIKE句を使用しSQLインジェクションを防止する（NFR-013）
- 在庫整合性: `stock_quantity >= 0`、`reserved_quantity <= stock_quantity`（DB CHECK制約 chk_products_stock, chk_products_reserved_le_stock）
- 在庫調整は単一DBトランザクション内でアトミックに実行し、`stock_movements`へ`reason=5`(manual_adjustment)として記録する
- ロールベースアクセス制御は`role:`ミドルウェアに委譲する（一覧: 全役割、登録・編集: admin、在庫調整: warehouse, admin）
- 参照したEARS要件: REQ-023, REQ-072, NFR-013, NFR-021
- 参照した設計文書: database-schema.sql（chk_products_*制約、stock_movementsテーブル）

## 4. 想定される使用例 🔵🟡
- 基本パターン: admin が製品を登録 → 一覧で品番・製品名検索 → warehouse が在庫を手動調整 → 変動履歴が記録される
- エッジケース: 調整結果が `reserved_quantity` を下回る場合は `StockAdjustmentViolatesIntegrityException` を発生させ、調整を拒否する（🔵 chk_products_reserved_le_stock制約より）
- 在庫アラート: `stock_quantity < alert_threshold` の製品には警告バッジを表示し、`stock_quantity === 0` の場合はさらに強調表示する（🟡 EDGE-010参照、表示方法はUI慣習からの推測）
- 参照したEARS要件: REQ-022, EDGE-010

## 5. 対応関係まとめ
- 参照した機能要件: REQ-020, REQ-021, REQ-022, REQ-023, REQ-072
- 参照した非機能要件: NFR-013, NFR-021
- 参照したEdgeケース: EDGE-010
- 参照した設計文書:
  - アーキテクチャ: architecture.md（Repository+Serviceパターン）
  - データベース: database-schema.sql（products, stock_movementsテーブル）
  - 型定義: data-types.php（ProductData）
  - API仕様: api-endpoints.md（製品管理エンドポイント群）
