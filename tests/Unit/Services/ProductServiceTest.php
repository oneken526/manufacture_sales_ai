<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\StockAdjustmentViolatesIntegrityException;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0006 単体テストケース1・3・4に対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0006.md 単体テスト要件
 */
class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductService
    {
        return $this->app->make(ProductService::class);
    }

    /**
     * 【テスト目的】: ProductService::adjustStock()が在庫数を増減し、stock_movementsへ手動調整(reason=5)として記録することを確認する
     * 【テスト内容】: stock_quantity=100, reserved_quantity=20の製品に対しadjustStock(+10)を呼び出す
     * 【期待される動作】: stock_quantityが110に更新され、reason=5・quantity_change=+10・operated_by・memoを含むレコードが1件作成される
     * 🔵 信頼性レベル: TASK-0006.md単体テストケース1（REQ-023, REQ-072）に直接基づく
     */
    public function test_adjust_stock_increases_quantity_and_records_stock_movement(): void
    {
        $service = $this->service();
        $operator = User::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 100,
            'reserved_quantity' => 20,
        ]);

        // 【実際の処理実行】: 在庫数を+10調整する
        $result = $service->adjustStock($product->id, 10, $operator->id, 'メモ');

        // 【結果検証】: 戻り値・DBともにstock_quantityが110へ更新されていることを確認する
        $this->assertSame(110, $result); // 【確認内容】: 戻り値が調整後の在庫数であることを確認 🔵
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 110,
        ]); // 【確認内容】: products.stock_quantityがDB上で更新されていることを確認 🔵

        // 【結果検証】: stock_movementsにreason=5(manual_adjustment)のレコードが1件作成されていることを確認する
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovementReason::MANUAL_ADJUSTMENT->value,
            'quantity_change' => 10,
            'operated_by' => $operator->id,
            'memo' => 'メモ',
            'related_order_id' => null,
        ]); // 【確認内容】: 在庫変動履歴が正しい内容で記録されることを確認 🔵
        $this->assertSame(1, $product->stockMovements()->count()); // 【確認内容】: 履歴レコードが1件のみ作成されることを確認 🔵
    }

    /**
     * 【テスト目的】: 調整後の実在庫が引当数を下回る操作が拒否され、在庫・履歴が更新されないことを確認する
     * 【テスト内容】: stock_quantity=20, reserved_quantity=15の製品に対しadjustStock(-10)を呼び出す（結果10 < 15）
     * 【期待される動作】: StockAdjustmentViolatesIntegrityExceptionがスローされ、在庫もstock_movementsも変化しない
     * 🔵 信頼性レベル: TASK-0006.md単体テストケース3（chk_products_reserved_le_stock制約）に直接基づく
     */
    public function test_adjust_stock_rejects_operation_that_would_make_stock_below_reserved(): void
    {
        $service = $this->service();
        $operator = User::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 20,
            'reserved_quantity' => 15,
        ]);

        // 【実際の処理実行】: 調整後にstock_quantity(10) < reserved_quantity(15)となる操作を試みる
        try {
            $service->adjustStock($product->id, -10, $operator->id);
            $this->fail('StockAdjustmentViolatesIntegrityExceptionがスローされるべきです');
        } catch (StockAdjustmentViolatesIntegrityException $e) {
            // 【結果検証】: 専用例外がスローされることを確認する 🔵
            $this->assertSame($product->id, $e->productId); // 【確認内容】: 例外が対象製品IDを保持していることを確認 🔵
        }

        // 【結果検証】: 在庫数が変更されず、stock_movementsにもレコードが作成されないことを確認する
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 20,
            'reserved_quantity' => 15,
        ]); // 【確認内容】: 在庫数が更新されずに維持されることを確認 🔵
        $this->assertSame(0, $product->stockMovements()->count()); // 【確認内容】: 在庫変動履歴が作成されないことを確認 🔵
    }

    /**
     * 【テスト目的】: availableQuantity()が実在庫数と引当数の差分を正しく返すことを確認する
     * 【テスト内容】: stock_quantity=50, reserved_quantity=12の製品に対しavailableQuantity()を呼び出す
     * 【期待される動作】: 戻り値が38となる
     * 🔵 信頼性レベル: TASK-0006.md単体テストケース4（ProductData::availableQuantity()）に直接基づく
     */
    public function test_available_quantity_returns_difference_between_stock_and_reserved(): void
    {
        $service = $this->service();
        $product = Product::factory()->create([
            'stock_quantity' => 50,
            'reserved_quantity' => 12,
        ]);

        // 【実際の処理実行】: availableQuantity()を呼び出す
        $available = $service->availableQuantity($product);

        // 【結果検証】: 実在庫(50)と引当数(12)の差である38が返されることを確認する
        $this->assertSame(38, $available); // 【確認内容】: 利用可能在庫数が正しく計算されることを確認 🔵
    }

    /**
     * 【テスト目的】: 在庫数がアラート閾値を下回る場合に警告フラグが立つことを確認する
     * 【テスト内容】: alert_threshold=10, stock_quantity=5の製品に対しisLowStock()を評価する
     * 【期待される動作】: 戻り値がtrueとなる
     * 🟡 信頼性レベル: TASK-0006.md単体テストケース2（REQ-022）に基づく
     */
    public function test_is_low_stock_returns_true_when_stock_quantity_is_below_alert_threshold(): void
    {
        $service = $this->service();
        $lowStockProduct = Product::factory()->create([
            'stock_quantity' => 5,
            'alert_threshold' => 10,
        ]);
        $sufficientStockProduct = Product::factory()->create([
            'stock_quantity' => 20,
            'alert_threshold' => 10,
        ]);

        // 【実際の処理実行】: 閾値を下回る製品・上回る製品それぞれについて警告判定を行う
        // 【結果検証】: 閾値を下回る製品はtrue、上回る製品はfalseとなることを確認する
        $this->assertTrue($service->isLowStock($lowStockProduct)); // 【確認内容】: 在庫数が閾値未満の場合に警告フラグが立つことを確認 🟡
        $this->assertFalse($service->isLowStock($sufficientStockProduct)); // 【確認内容】: 在庫数が閾値以上の場合は警告フラグが立たないことを確認 🟡
    }
}
