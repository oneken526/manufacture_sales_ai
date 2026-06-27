<?php

namespace Tests\Feature\Shipments;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0010 統合テスト: ShipmentController（TC-F01〜TC-F04）
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0010/shipment-testcases.md TC-F01〜TC-F04
 */
class ShipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * テストデータ準備ヘルパー
     *
     * @param  array<int, array{quantity: int, stock: int, reserved: int}>  $items
     * @return array{order: SalesOrder, products: Product[]}
     */
    private function prepareOrder(OrderStatus $status, array $items, ?User $creator = null): array
    {
        $customer = Customer::factory()->create();
        $creator = $creator ?? User::factory()->create(['role' => UserRole::SALES]);

        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'confirmed_at' => now(),
            'created_by' => $creator->id,
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

        return ['order' => $order, 'products' => $products];
    }

    /**
     * 【テスト目的】 TC-F01: 出荷指示一覧画面がwarehouseユーザーに表示される
     * 【テスト内容】 warehouseロールでGET /shipmentsにアクセスする
     * 【期待される動作】 200 OKが返り、出荷指示済み受注が表示される
     * 🔵 REQ-050, api-endpoints.mdより
     */
    public function test_warehouse_user_can_view_shipments_index(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $this->prepareOrder(
            OrderStatus::SHIPPING_INSTRUCTED,
            [['quantity' => 5, 'stock' => 50, 'reserved' => 5]]
        );

        $response = $this->actingAs($warehouse)->get('/shipments');

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F02: 出荷完了登録フローが正常に動作する
     * 【テスト内容】 warehouseロールでPOST /shipments/{order}/completeを実行する
     * 【期待される動作】 在庫減算・stock_movements記録・status=SHIPPED・shipmentsレコード作成
     * 🔵 REQ-051, REQ-072, dataflow.md機能2より
     */
    public function test_warehouse_user_can_complete_shipment(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        ['order' => $order, 'products' => $products] = $this->prepareOrder(
            OrderStatus::SHIPPING_INSTRUCTED,
            [['quantity' => 10, 'stock' => 100, 'reserved' => 20]]
        );

        $response = $this->actingAs($warehouse)
            ->post("/shipments/{$order->id}/complete");

        $response->assertRedirect();

        $products[0]->refresh();
        $this->assertEquals(90, $products[0]->stock_quantity);
        $this->assertEquals(10, $products[0]->reserved_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'related_order_id' => $order->id,
            'reason' => StockMovementReason::SHIPMENT->value,
        ]);

        $this->assertDatabaseHas('shipments', [
            'sales_order_id' => $order->id,
        ]);

        $order->refresh();
        $this->assertEquals(OrderStatus::SHIPPED, $order->status);
    }

    /**
     * 【テスト目的】 TC-F03: 返品登録フローが正常に動作する
     * 【テスト内容】 warehouseロールでPOST /shipments/{shipment}/returnを実行する
     * 【期待される動作】 在庫加算・stock_movements記録・status=RETURNED
     * 🔵 REQ-053より
     */
    public function test_warehouse_user_can_process_return(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        ['order' => $order, 'products' => $products] = $this->prepareOrder(
            OrderStatus::SHIPPED,
            [['quantity' => 10, 'stock' => 90, 'reserved' => 10]]
        );

        $shipment = Shipment::factory()->create([
            'sales_order_id' => $order->id,
            'shipped_at' => now()->subHour(),
            'shipped_by' => $warehouse->id,
        ]);

        $response = $this->actingAs($warehouse)
            ->post("/shipments/{$shipment->id}/return", [
                'return_reason' => '製品不良のため',
            ]);

        $response->assertRedirect();

        $products[0]->refresh();
        $this->assertEquals(100, $products[0]->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'related_order_id' => $order->id,
            'reason' => StockMovementReason::RETURN_RECEIVED->value,
        ]);

        $order->refresh();
        $this->assertEquals(OrderStatus::RETURNED, $order->status);

        $shipment->refresh();
        $this->assertEquals('製品不良のため', $shipment->return_reason);
    }

    /**
     * 【テスト目的】 TC-F04: warehouseロールは請求書エンドポイントにアクセスできない
     * 【テスト内容】 warehouseロールでGET /invoicesにアクセスする
     * 【期待される動作】 403 Forbiddenが返却される
     * 🔵 REQ-003より
     */
    public function test_warehouse_user_cannot_access_invoices(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);

        $response = $this->actingAs($warehouse)->get('/invoices');

        $response->assertStatus(403);
    }

    /**
     * 【テスト目的】 TC-F05: 未認証ユーザーは出荷管理にアクセスできない
     * 【テスト内容】 未ログイン状態でGET /shipmentsにアクセスする
     * 【期待される動作】 302リダイレクト（ログイン画面）
     * 🔵 Laravel認証基盤より
     */
    public function test_unauthenticated_user_is_redirected_from_shipments(): void
    {
        $response = $this->get('/shipments');

        $response->assertRedirect('/login');
    }
}
