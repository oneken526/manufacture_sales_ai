<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0010 単体テスト: ShipmentService（TC-U01〜TC-U04）
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0010/shipment-testcases.md TC-U01〜TC-U04
 */
class ShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ShipmentService
    {
        return $this->app->make(ShipmentService::class);
    }

    /**
     * テストデータ準備ヘルパー
     *
     * @param  array<int, array{quantity: int, stock: int, reserved: int}>  $items
     * @return array{order: SalesOrder, products: Product[], user: User}
     */
    private function prepareOrder(OrderStatus $status, array $items): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::WAREHOUSE]);
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'created_by' => $user->id,
        ]);

        $products = [];
        foreach ($items as $itemDef) {
            $product = Product::factory()->create([
                'stock_quantity' => $itemDef['stock'],
                'reserved_quantity' => $itemDef['reserved'],
            ]);
            SalesOrderItem::factory()->create([
                'sales_order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $itemDef['quantity'],
                'unit_price' => 1000,
            ]);
            $products[] = $product;
        }

        return ['order' => $order, 'products' => $products, 'user' => $user];
    }

    /**
     * 【テスト目的】 TC-U01: 出荷完了処理が在庫を減算しstock_movementsに記録する
     * 【テスト内容】 SHIPPING_INSTRUCTEDの受注に対してcomplete()を実行する
     * 【期待される動作】 在庫実減算・stock_movements記録・shipments作成・status=SHIPPEDが実行される
     * 🔵 REQ-051, REQ-072, dataflow.md機能2より
     */
    public function test_complete_decrements_stock_and_records_movement(): void
    {
        ['order' => $order, 'products' => $products, 'user' => $user] = $this->prepareOrder(
            OrderStatus::SHIPPING_INSTRUCTED,
            [['quantity' => 10, 'stock' => 100, 'reserved' => 20]]
        );

        $this->service()->complete($order, $user->id);

        $products[0]->refresh();
        $this->assertEquals(90, $products[0]->stock_quantity);
        $this->assertEquals(10, $products[0]->reserved_quantity);

        $movement = StockMovement::where('related_order_id', $order->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(StockMovementReason::SHIPMENT, $movement->reason);
        $this->assertEquals(-10, $movement->quantity_change);
        $this->assertEquals($user->id, $movement->operated_by);

        $shipment = Shipment::where('sales_order_id', $order->id)->first();
        $this->assertNotNull($shipment);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertEquals($user->id, $shipment->shipped_by);

        $order->refresh();
        $this->assertEquals(OrderStatus::SHIPPED, $order->status);
    }

    /**
     * 【テスト目的】 TC-U02: 返品処理が在庫を加算しstock_movementsに記録する
     * 【テスト内容】 SHIPPEDの受注に対してprocessReturn()を実行する
     * 【期待される動作】 在庫加算・stock_movements記録・status=RETURNEDが実行される
     * 🔵 REQ-053より
     */
    public function test_process_return_increments_stock_and_records_movement(): void
    {
        ['order' => $order, 'products' => $products, 'user' => $user] = $this->prepareOrder(
            OrderStatus::SHIPPED,
            [['quantity' => 10, 'stock' => 90, 'reserved' => 10]]
        );

        $shipment = Shipment::factory()->create([
            'sales_order_id' => $order->id,
            'shipped_at' => now()->subHour(),
            'shipped_by' => $user->id,
        ]);

        $this->service()->processReturn($shipment, '製品不良のため', $user->id);

        $products[0]->refresh();
        $this->assertEquals(100, $products[0]->stock_quantity);

        $movement = StockMovement::where('related_order_id', $order->id)
            ->where('reason', StockMovementReason::RETURN_RECEIVED)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->quantity_change);

        $order->refresh();
        $this->assertEquals(OrderStatus::RETURNED, $order->status);

        $shipment->refresh();
        $this->assertNotNull($shipment->returned_at);
        $this->assertEquals('製品不良のため', $shipment->return_reason);
    }

    /**
     * 【テスト目的】 TC-U03: 出荷指示未発行の受注はcomplete()を拒否する
     * 【テスト内容】 CONFIRMED状態の受注に対してcomplete()を実行する
     * 【期待される動作】 InvalidArgumentExceptionがスローされ、在庫・ステータスが変更されない
     * 🟡 dataflow.md受注ステータス遷移より
     */
    public function test_complete_rejects_order_not_in_shipping_instructed_status(): void
    {
        ['order' => $order, 'products' => $products, 'user' => $user] = $this->prepareOrder(
            OrderStatus::CONFIRMED,
            [['quantity' => 10, 'stock' => 100, 'reserved' => 20]]
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->complete($order, $user->id);

        $products[0]->refresh();
        $this->assertEquals(100, $products[0]->stock_quantity);
        $order->refresh();
        $this->assertEquals(OrderStatus::CONFIRMED, $order->status);
    }

    /**
     * 【テスト目的】 TC-U04: 在庫が不足している場合にcomplete()がロールバックする
     * 【テスト内容】 stock_quantityが明細数量を下回る不整合状態でcomplete()を実行する
     * 【期待される動作】 例外がスローされ在庫数が負値にならない
     * 🔵 dataflow.md「データ整合性の保証」より
     */
    public function test_complete_rolls_back_when_stock_insufficient(): void
    {
        ['order' => $order, 'products' => $products, 'user' => $user] = $this->prepareOrder(
            OrderStatus::SHIPPING_INSTRUCTED,
            [['quantity' => 10, 'stock' => 5, 'reserved' => 5]]
        );

        $this->expectException(\RuntimeException::class);

        $this->service()->complete($order, $user->id);

        $products[0]->refresh();
        $this->assertGreaterThanOrEqual(0, $products[0]->stock_quantity);
        $this->assertGreaterThanOrEqual(0, $products[0]->reserved_quantity);
    }
}
