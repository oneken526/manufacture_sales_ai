# TASK-0011 在庫管理機能 Greenフェーズ実装記録

## 実装方針
- 閲覧専用機能のため、InventoryControllerに在庫一覧・変動履歴を集約
- availableQuantity = stock_quantity - reserved_quantity はBladeテンプレート内で計算（モデルメソッド不要）
- アラート判定は stock_quantity <= alert_threshold

## 成果物

### 新規作成ファイル
| ファイル | 説明 |
|---|---|
| `app/Http/Controllers/InventoryController.php` | index()・movements() 実装 |
| `resources/views/inventory/index.blade.php` | 在庫一覧ビュー（アラートバッジ・利用可能数表示） |
| `resources/views/inventory/movements.blade.php` | 変動履歴ビュー（フィルタ・日時降順） |
| `tests/Feature/Inventory/InventoryManagementTest.php` | 統合テスト（TC-F01〜TC-F06）|

### 変更ファイル
| ファイル | 変更内容 |
|---|---|
| `routes/web.php` | /inventory, /inventory/{product}/movementsルート追加 |

## テスト実行結果
```
Tests: 6 passed (TC-F01〜TC-F06)
全体: 141 tests passed (139 passed, 2 skipped)
```
