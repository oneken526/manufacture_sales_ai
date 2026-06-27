<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0016 統合テスト3: 4役割×主要機能 アクセス制御マトリクステスト
 *
 * @see acceptance-criteria.md TC-003-01, TC-003-02, REQ-002, REQ-003, REQ-064
 */
class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    // ─────────────────────────────────────────
    // REQ-003: warehouse は請求書操作不可
    // ─────────────────────────────────────────

    /**
     * 【テスト目的】 TC-003-01: warehouse権限が請求書URLに直接アクセスすると403
     * 🔵 REQ-003より
     */
    public function test_warehouse_cannot_access_invoices(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $order = SalesOrder::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::SHIPPED,
            'created_by' => $warehouse->id,
        ]);

        $this->actingAs($warehouse)->get('/invoices')->assertStatus(403);
        $this->actingAs($warehouse)->post("/invoices/{$order->id}")->assertStatus(403);
    }

    /**
     * 【テスト目的】 TC-003-02: sales権限がユーザー管理URLに直接アクセスすると403
     * 🟡 REQ-002より
     */
    public function test_sales_cannot_access_admin_user_management(): void
    {
        $sales = $this->user(UserRole::SALES);

        $this->actingAs($sales)->get('/admin/users')->assertStatus(403);
        $this->actingAs($sales)->get('/admin/users/create')->assertStatus(403);
    }

    // ─────────────────────────────────────────
    // REQ-064: accounting/admin のみ請求書操作可能
    // ─────────────────────────────────────────

    /**
     * 【テスト目的】 accounting権限は請求書一覧にアクセスできる
     * 🔵 REQ-064より
     */
    public function test_accounting_can_access_invoices(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);

        $this->actingAs($accounting)->get('/invoices')->assertStatus(200);
    }

    /**
     * 【テスト目的】 admin権限はすべての機能にアクセスできる
     * 🔵 REQ-002より
     */
    public function test_admin_can_access_all_major_features(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        $this->actingAs($admin)->get('/customers')->assertStatus(200);
        $this->actingAs($admin)->get('/products')->assertStatus(200);
        $this->actingAs($admin)->get('/quotations')->assertStatus(200);
        $this->actingAs($admin)->get('/orders')->assertStatus(200);
        $this->actingAs($admin)->get('/shipments')->assertStatus(200);
        $this->actingAs($admin)->get('/invoices')->assertStatus(200);
        $this->actingAs($admin)->get('/inventory')->assertStatus(200);
        $this->actingAs($admin)->get('/reports/sales')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/users')->assertStatus(200);
    }

    /**
     * 【テスト目的】 sales権限はレポートを閲覧できるが出荷管理には入れない
     * 🔵 REQ-084, api-endpoints.mdより
     */
    public function test_sales_can_view_reports_but_not_shipments(): void
    {
        $sales = $this->user(UserRole::SALES);

        $this->actingAs($sales)->get('/reports/sales')->assertStatus(200);
        $this->actingAs($sales)->get('/shipments')->assertStatus(403);
        $this->actingAs($sales)->get('/inventory')->assertStatus(403);
    }

    /**
     * 【テスト目的】 warehouse権限は出荷管理・在庫管理にアクセスできる
     * 🔵 api-endpoints.mdより
     */
    public function test_warehouse_can_access_shipments_and_inventory(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $product = Product::factory()->create();

        $this->actingAs($warehouse)->get('/shipments')->assertStatus(200);
        $this->actingAs($warehouse)->get('/inventory')->assertStatus(200);
        $this->actingAs($warehouse)->get("/inventory/{$product->id}/movements")->assertStatus(200);
    }

    // ─────────────────────────────────────────
    // Edge case: 未認証アクセス
    // ─────────────────────────────────────────

    /**
     * 【テスト目的】 未認証ユーザーは全主要ルートにアクセスできない
     * 🔵 acceptance-criteria.md TC-001より
     */
    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->get('/customers')->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
        $this->get('/invoices')->assertRedirect('/login');
        $this->get('/reports/sales')->assertRedirect('/login');
    }
}
