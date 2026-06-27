<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0011 統合テスト: InventoryController
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0011/inventory-testcases.md
 */
class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】 TC-F01: warehouse/adminが在庫一覧画面にアクセスできる
     * 【テスト内容】 warehouseロールでGET /inventoryにアクセスする
     * 【期待される動作】 200 OKが返りstock_quantity/reserved_quantity/availableQuantityが表示される
     * 🔵 REQ-070, api-endpoints.mdより
     */
    public function test_warehouse_can_view_inventory_index(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        Product::factory()->create(['stock_quantity' => 100, 'reserved_quantity' => 30]);

        $response = $this->actingAs($warehouse)->get('/inventory');

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F02: 在庫一覧にavailableQuantityが正しく表示される
     * 【テスト内容】 stock_quantity=100, reserved_quantity=30の製品の利用可能数を確認する
     * 【期待される動作】 利用可能数=70が画面に表示される
     * 🔵 REQ-070, data-types.phpのavailableQuantity()より
     */
    public function test_inventory_index_shows_available_quantity(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        Product::factory()->create([
            'product_name' => '在庫テスト製品',
            'stock_quantity' => 100,
            'reserved_quantity' => 30,
        ]);

        $response = $this->actingAs($warehouse)->get('/inventory');

        $response->assertStatus(200);
        $response->assertSee('70'); // availableQuantity = 100 - 30
    }

    /**
     * 【テスト目的】 TC-F03: 在庫アラート対象製品が強調表示される
     * 【テスト内容】 stock_quantity <= alert_thresholdの製品がアラート表示される
     * 【期待される動作】 アラート対象製品に警告バッジ/強調が表示される
     * 🟡 REQ-022より
     */
    public function test_inventory_index_shows_alert_for_low_stock(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        Product::factory()->create([
            'product_name' => '在庫不足製品',
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'alert_threshold' => 10,
        ]);

        $response = $this->actingAs($warehouse)->get('/inventory');

        $response->assertStatus(200);
        $response->assertSee('在庫不足');
    }

    /**
     * 【テスト目的】 TC-F04: 在庫変動履歴画面が表示される
     * 【テスト内容】 warehouseロールでGET /inventory/{product}/movementsにアクセスする
     * 【期待される動作】 200 OKが返り変動履歴が日時降順で表示される
     * 🟡 REQ-072より
     */
    public function test_warehouse_can_view_stock_movements(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'reason' => StockMovementReason::MANUAL_ADJUSTMENT,
            'quantity_change' => 10,
            'operated_by' => $warehouse->id,
            'created_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($warehouse)->get("/inventory/{$product->id}/movements");

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F05: salesロールは在庫一覧にアクセスできない
     * 【テスト内容】 salesロールでGET /inventoryにアクセスする
     * 【期待される動作】 403 Forbiddenが返却される
     * 🔵 REQ-070, api-endpoints.mdの権限テーブルより
     */
    public function test_sales_user_cannot_access_inventory(): void
    {
        $sales = $this->user(UserRole::SALES);

        $response = $this->actingAs($sales)->get('/inventory');

        $response->assertStatus(403);
    }

    /**
     * 【テスト目的】 TC-F06: 未認証ユーザーは在庫画面にアクセスできない
     * 【テスト内容】 未ログイン状態でGET /inventoryにアクセスする
     * 【期待される動作】 302リダイレクト（ログイン画面）
     * 🔵 Laravel認証基盤より
     */
    public function test_unauthenticated_user_is_redirected_from_inventory(): void
    {
        $response = $this->get('/inventory');

        $response->assertRedirect('/login');
    }
}
