# Redフェーズ記録: 受注管理機能

**タスクID**: TASK-0009
**実施日**: 2026-06-27
**フェーズ**: Red（失敗するテストケースの作成）

---

## 作成したテストファイル

| ファイル | テスト数 | 対応TC |
|---|---|---|
| `tests/Unit/Services/OrderServiceTest.php` | 9件 | TC-01〜TC-08 + TC-20 |
| `tests/Feature/Orders/OrderManagementTest.php` | 12件 | TC-09〜TC-19, TC-21, TC-22 |
| `database/factories/SalesOrderItemFactory.php` | - | テスト用ファクトリ（新規） |

## テスト実行結果（Redフェーズ確認）

```
Tests: 21, Passed: 0, Failed: 5, Errors: 16
```

**失敗パターン1: OrderService 未実装（Unit Tests）**
```
Target class [App\Services\OrderService] does not exist.
```
→ `OrderService` クラスが未実装のため、DIコンテナが解決できない

**失敗パターン2: ルート未定義（Feature Tests）**
```
Route [orders.index] not defined.
Route [orders.show] not defined.
Route [orders.cancel] not defined.
Route [orders.shipping_instruction] not defined.
Route [orders.update] not defined.
Route [orders.edit] not defined.
```
→ `routes/web.php` に受注管理ルートが未追加

---

## 作成したテストケース一覧

### 単体テスト（OrderServiceTest）

| # | テスト名 | 対応TC | 信頼性 |
|---|---|---|---|
| 1 | `test_cancel_releases_reserved_quantity_and_records_stock_movement` | TC-01 | 🔵 |
| 2 | `test_cancel_works_for_shipping_instructed_status` | TC-02 | 🔵 |
| 3 | `test_cancel_throws_exception_when_order_is_already_shipped` | TC-03 | 🔵 |
| 4 | `test_cancel_throws_exception_when_order_is_invoiced` | TC-04 | 🟡 |
| 5 | `test_cancel_ensures_reserved_quantity_does_not_go_negative` | TC-05 | 🔵 |
| 6 | `test_cancel_reduces_reserved_quantity_for_all_items` | TC-20 | 🔵 |
| 7 | `test_issue_shipping_instruction_changes_status_to_shipping_instructed` | TC-06 | 🔵 |
| 8 | `test_issue_shipping_instruction_throws_exception_when_already_shipping_instructed` | TC-07 | 🔵 |
| 9 | `test_issue_shipping_instruction_throws_exception_for_cancelled_order` | TC-08 | 🔵 |

### 統合テスト（OrderManagementTest）

| # | テスト名 | 対応TC | 信頼性 |
|---|---|---|---|
| 1 | `test_sales_user_can_view_orders_index` | TC-09 | 🔵 |
| 2 | `test_sales_user_can_filter_orders_by_status` | TC-10 | 🟡 |
| 3 | `test_sales_user_can_view_order_detail` | TC-11 | 🔵 |
| 4 | `test_sales_user_can_cancel_order_and_reserved_quantity_is_released` | TC-12 | 🔵 |
| 5 | `test_sales_user_can_issue_shipping_instruction` | TC-13 | 🔵 |
| 6 | `test_only_admin_can_edit_confirmed_order` | TC-14 | 🔵 |
| 7 | `test_sales_user_gets_403_when_accessing_edit_page` | TC-15 | 🟡 |
| 8 | `test_cannot_cancel_shipped_order_and_shows_error_message` | TC-16 | 🔵 |
| 9 | `test_cannot_issue_shipping_instruction_twice` | TC-17 | 🔵 |
| 10 | `test_warehouse_user_cannot_access_orders` | TC-18 | 🟡 |
| 11 | `test_accounting_user_cannot_cancel_order` | TC-21 | 🔵 |
| 12 | `test_orders_index_paginates_50_per_page` | TC-22 | 🔵 |

---

## Greenフェーズで実装すべき内容

### 必須実装ファイル

1. **`app/Services/OrderService.php`** — `cancel()` / `issueShippingInstruction()` メソッド
2. **`app/Repositories/Contracts/SalesOrderRepositoryInterface.php`** — リポジトリインターフェース
3. **`app/Repositories/Eloquent/EloquentSalesOrderRepository.php`** — Eloquent実装
4. **`app/Policies/OrderPolicy.php`** — `update()` でadmin判定
5. **`app/Http/Controllers/OrderController.php`** — index/show/edit/update/cancel/issueShippingInstruction
6. **`app/Http/Requests/UpdateSalesOrderRequest.php`** — 受注編集バリデーション
7. **`routes/web.php`** — 受注管理ルート追加（orders.index/show/edit/update/cancel/shipping_instruction）
8. **`app/Providers/AppServiceProvider.php`** — SalesOrderRepositoryInterface のDIバインド追加
9. **Bladeビュー** — `resources/views/orders/{index,show,edit}.blade.php`

### 実装上の注意事項

- `cancel()` は必ず `DB::transaction()` + `lockForUpdate()` でアトミックに実行
- キャンセル可能なステータス: `CONFIRMED(1)` / `SHIPPING_INSTRUCTED(2)` のみ
- `reserved_quantity` が負値にならないことをアプリレベルで検証
- `issueShippingInstruction()` は `CONFIRMED(1)` → `SHIPPING_INSTRUCTED(2)` のみ許可
- `OrderPolicy` を `AuthServiceProvider` または `AppServiceProvider` に登録
- 統合テストでは `orders.cancel` / `orders.shipping_instruction` のルート名でアクセス
