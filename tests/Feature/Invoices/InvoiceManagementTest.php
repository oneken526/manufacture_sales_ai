<?php

namespace Tests\Feature\Invoices;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0012 統合テスト: InvoiceController
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0012/invoice-testcases.md
 */
class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function prepareShippedOrder(?User $creator = null): SalesOrder
    {
        $customer = Customer::factory()->create();
        $creator = $creator ?? User::factory()->create(['role' => UserRole::SALES]);
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::SHIPPED,
            'created_by' => $creator->id,
        ]);
        $product = Product::factory()->create(['unit_price' => 1000]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 1000,
        ]);
        return $order;
    }

    /**
     * 【テスト目的】 TC-F01: accounting権限で請求書一覧を表示できる
     * 【テスト内容】 accountingロールでGET /invoicesにアクセスする
     * 【期待される動作】 200 OKが返る
     * 🔵 REQ-064, api-endpoints.mdより
     */
    public function test_accounting_can_view_invoices_index(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);

        $response = $this->actingAs($accounting)->get('/invoices');

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F02: accounting権限で請求書を発行できる
     * 【テスト内容】 accountingロールでPOST /invoices/{order}を実行する
     * 【期待される動作】 invoicesレコードが作成されリダイレクト
     * 🔵 REQ-060, REQ-061, dataflow.md機能3より
     */
    public function test_accounting_can_issue_invoice(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);
        $order = $this->prepareShippedOrder();

        $response = $this->actingAs($accounting)->post("/invoices/{$order->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $order->id,
            'payment_status' => PaymentStatus::UNPAID->value,
        ]);
    }

    /**
     * 【テスト目的】 TC-F03: 同一受注への二重発行がUIで防止される
     * 【テスト内容】 発行済みの受注に対してPOST /invoices/{order}を再度実行する
     * 【期待される動作】 エラーメッセージが表示され2件目のinvoiceは作成されない
     * 🔵 EDGE-004より
     */
    public function test_duplicate_invoice_is_prevented(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);
        $order = $this->prepareShippedOrder();

        $this->actingAs($accounting)->post("/invoices/{$order->id}");
        $response = $this->actingAs($accounting)->post("/invoices/{$order->id}");

        $response->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
    }

    /**
     * 【テスト目的】 TC-F04: warehouse権限では請求書発行できない（REQ-064）
     * 【テスト内容】 warehouseロールでPOST /invoices/{order}を実行する
     * 【期待される動作】 403 Forbiddenが返却される
     * 🔵 REQ-064より
     */
    public function test_warehouse_cannot_issue_invoice(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $order = $this->prepareShippedOrder();

        $response = $this->actingAs($warehouse)->post("/invoices/{$order->id}");

        $response->assertStatus(403);
    }

    /**
     * 【テスト目的】 TC-F05: 入金ステータスの手動更新が動作する
     * 【テスト内容】 accountingロールでPUT /invoices/{invoice}/payment-statusを実行する
     * 【期待される動作】 payment_statusがPAIDに更新される
     * 🔵 REQ-062より
     */
    public function test_accounting_can_update_payment_status(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);
        $order = $this->prepareShippedOrder();

        $this->actingAs($accounting)->post("/invoices/{$order->id}");
        $invoice = Invoice::where('sales_order_id', $order->id)->firstOrFail();

        $response = $this->actingAs($accounting)->put("/invoices/{$invoice->id}/payment-status", [
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(PaymentStatus::PAID, $invoice->payment_status);
    }
}
