# Greenフェーズ記録: 受注管理機能

**タスクID**: TASK-0009
**実施日**: 2026-06-27
**フェーズ**: Green（テストを通す最小実装）

---

## テスト実行結果

```
Tests: 21, Passed: 21, Assertions: 49 — 全テスト通過 ✅
```

---

## 作成・変更したファイル一覧

| ファイル | 状態 | 概要 |
|---|---|---|
| `app/Repositories/Contracts/SalesOrderRepositoryInterface.php` | 新規 | リポジトリインターフェース |
| `app/Repositories/Eloquent/EloquentSalesOrderRepository.php` | 新規 | Eloquent実装（ステータスフィルタ付きpaginate） |
| `app/Services/OrderService.php` | 新規 | cancel() / issueShippingInstruction() |
| `app/Policies/OrderPolicy.php` | 新規 | update()でadmin判定 |
| `app/Http/Controllers/OrderController.php` | 新規 | index/show/edit/update/cancel/issueShippingInstruction |
| `app/Http/Requests/UpdateSalesOrderRequest.php` | 新規 | 受注編集バリデーション |
| `app/Models/SalesOrderItem.php` | 変更 | HasFactory トレイト追加 |
| `database/factories/SalesOrderItemFactory.php` | 新規 | テスト用ファクトリ |
| `resources/views/orders/index.blade.php` | 新規 | 受注一覧（ステータスフィルタ） |
| `resources/views/orders/show.blade.php` | 新規 | 受注詳細（キャンセル・出荷指示ボタン） |
| `resources/views/orders/edit.blade.php` | 新規 | 受注編集フォーム（adminのみ） |
| `routes/web.php` | 変更 | 受注管理ルート追加 |
| `app/Providers/AppServiceProvider.php` | 変更 | DIバインディング・OrderPolicy登録追加 |

---

## 実装方針・判断理由

### cancel() の実装
- `DB::transaction()` 内で `lockForUpdate()` を使用して競合を防止
- キャンセル可能なステータス: `CONFIRMED(1)` / `SHIPPING_INSTRUCTED(2)` のみ（ガード処理）
- `reserved_quantity` 減算前に負値チェックを実施（`RuntimeException` スロー）
- `stock_movements` の `quantity_change` を負値（`-item->quantity`）で記録

### issueShippingInstruction() の実装
- `CONFIRMED(1)` のみ `SHIPPING_INSTRUCTED(2)` への遷移を許可
- それ以外は `InvalidArgumentException` をスロー

### OrderPolicy
- `authorize()` は `AuthorizesRequests` トレイトが必要 → `OrderController` に追加
- `Gate::policy(SalesOrder::class, OrderPolicy::class)` で手動登録（`OrderPolicy` は自動検出されない命名のため）

---

## Refactorフェーズの候補

1. `cancel()` と `issueShippingInstruction()` のキャンセル可能ステータス判定をEnumメソッドに移動
2. `stock_movements` の `memo` 文字列生成をValueObjectやEnumのラベルに統一
3. ルーティングの `edit`/`update` が `role:admin` ミドルウェアと `OrderPolicy` の二重チェックになっているため整理を検討
