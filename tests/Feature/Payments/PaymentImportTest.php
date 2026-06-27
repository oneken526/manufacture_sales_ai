<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * TASK-0013 統合テスト: PaymentController
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0013/payment-import-testcases.md
 */
class PaymentImportTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】 TC-F01: accountingユーザーがインポート画面にアクセスできる
     * 【テスト内容】 accountingロールでGET /payments/importにアクセスする
     * 【期待される動作】 200 OKが返る
     * 🔵 api-endpoints.mdより
     */
    public function test_accounting_can_view_import_form(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);

        $response = $this->actingAs($accounting)->get('/payments/import');

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F02: CSVアップロードで照合結果が表示される
     * 【テスト内容】 accountingロールでPOST /payments/importを実行する
     * 【期待される動作】 成功/未照合件数を含む結果が表示される
     * 🔵 dataflow.md機能4より
     */
    public function test_csv_import_shows_result(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::SHIPPED,
            'created_by' => $accounting->id,
        ]);
        $product = Product::factory()->create(['unit_price' => 50000]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);
        $invoice = $this->app->make(InvoiceService::class)->issue($order, $accounting->id);

        $csvContent = "paid_at,transfer_name,amount,description\n";
        $csvContent .= "2026-06-27,テスト株式会社,50000,{$invoice->invoice_number}\n";

        $file = UploadedFile::fake()->createWithContent('bank.csv', $csvContent);

        $response = $this->actingAs($accounting)->post('/payments/import', [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertSee('1件成功');
    }

    /**
     * 【テスト目的】 TC-F03: warehouseロールはインポートにアクセスできない
     * 【テスト内容】 warehouseロールでGET /payments/importにアクセスする
     * 【期待される動作】 403 Forbiddenが返却される
     * 🔵 api-endpoints.mdより
     */
    public function test_warehouse_cannot_access_import(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);

        $response = $this->actingAs($warehouse)->get('/payments/import');

        $response->assertStatus(403);
    }
}
