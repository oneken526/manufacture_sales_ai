# TASK-0006: 製品マスタ管理機能 - テストケース一覧

## 単体テスト

### Tests\Unit\Services\ProductServiceTest

| # | テスト名 | 内容 | 信頼性 | 結果 |
|---|---|---|---|---|
| 1 | test_adjust_stock_increases_quantity_and_records_stock_movement | stock_quantity=100, reserved_quantity=20の製品に対しadjustStock(+10)を実行し、stock_quantityが110に更新され、stock_movementsにreason=5・quantity_change=+10・operated_by・memoを含むレコードが1件作成されることを確認 | 🔵 | ✅成功 |
| 2 | test_adjust_stock_rejects_operation_that_would_make_stock_below_reserved | stock_quantity=20, reserved_quantity=15の製品に対しadjustStock(-10)（結果10<15）を実行し、StockAdjustmentViolatesIntegrityExceptionがスローされ在庫・履歴とも変化しないことを確認 | 🔵 | ✅成功 |
| 3 | test_available_quantity_returns_difference_between_stock_and_reserved | stock_quantity=50, reserved_quantity=12の製品でavailableQuantity()が38を返すことを確認 | 🔵 | ✅成功 |
| 4 | test_is_low_stock_returns_true_when_stock_quantity_is_below_alert_threshold | alert_threshold=10に対しstock_quantity=5はtrue、stock_quantity=20はfalseを返すことを確認 | 🟡 | ✅成功 |

### Tests\Unit\Repositories\ProductRepositoryTest

| # | テスト名 | 内容 | 信頼性 | 結果 |
|---|---|---|---|---|
| 5 | test_search_returns_products_matching_product_code_or_product_name | 品番・製品名の部分一致（LIKE OR）検索が正しく機能することを確認 | 🔵 | ✅成功 |
| 6 | test_paginate_returns_fifty_items_per_page | 60件登録時に1ページ目50件・2ページ目10件・総件数60件となることを確認 | 🔵 | ✅成功 |

## 統合テスト

### Tests\Feature\Products\ProductManagementTest

| # | テスト名 | 内容 | 信頼性 | 結果 |
|---|---|---|---|---|
| 7 | test_product_can_be_registered_searched_and_stock_adjusted_with_movement_recorded | admin権限で製品登録→品番検索→warehouse権限で在庫調整(-20)を行い、stock_quantityの更新とstock_movements（reason=5）への記録を一連のフローで確認 | 🔵 | ✅成功 |
| 8 | test_products_below_alert_threshold_are_visually_marked_in_index | alert_thresholdを上回る製品・下回る製品を用意し、一覧画面で閾値を下回る製品の行にのみ警告バッジ「在庫不足」が表示されることを確認 | 🟡 | ✅成功 |

## テスト実行結果

```
php artisan test --filter=Product
{"tool":"phpunit","result":"passed","tests":11,"passed":10,"assertions":53,"skipped":1}
```
（残り1件は本タスクと無関係な既存スキップテスト。詳細はリポジトリ全体のテストを参照）

```
php artisan test
{"tool":"phpunit","result":"passed","tests":85,"passed":83,"assertions":294,"skipped":2}
```
全85テスト成功（既存スキップ2件を除く）。本タスクで追加した8テストすべてが成功し、既存テストへの影響もないことを確認した。
