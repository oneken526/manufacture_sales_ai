<?php

namespace Tests\Feature\Reports;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0014 統合テスト: ReportController
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0014/report-testcases.md
 */
class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】 TC-F01: sales権限でレポート画面を表示できる
     * 【テスト内容】 salesロールでGET /reports/salesにアクセスする
     * 【期待される動作】 200 OKが返る
     * 🔵 REQ-084より
     */
    public function test_sales_can_view_report(): void
    {
        $sales = $this->user(UserRole::SALES);

        $response = $this->actingAs($sales)->get('/reports/sales');

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F02: accounting権限でレポート画面を表示できる
     * 【テスト内容】 accountingロールでGET /reports/salesにアクセスする
     * 【期待される動作】 200 OKが返る
     * 🔵 REQ-084より
     */
    public function test_accounting_can_view_report(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);

        $response = $this->actingAs($accounting)->get('/reports/sales');

        $response->assertStatus(200);
    }

    /**
     * 【テスト目的】 TC-F03: warehouse権限ではレポートを閲覧できない
     * 【テスト内容】 warehouseロールでGET /reports/salesにアクセスする
     * 【期待される動作】 403 Forbiddenが返る
     * 🔵 REQ-084より
     */
    public function test_warehouse_cannot_view_report(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);

        $response = $this->actingAs($warehouse)->get('/reports/sales');

        $response->assertStatus(403);
    }

    /**
     * 【テスト目的】 TC-F04: 売上データがある場合に正しくレポートが表示される
     * 【テスト内容】 受注データを登録してレポート画面を表示する
     * 【期待される動作】 売上合計が画面に表示される
     * 🔵 REQ-080・dataflow.md機能5より
     */
    public function test_report_displays_correct_sales_data(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create(['company_name' => 'テスト顧客']);
        $product = Product::factory()->create();
        $creator = User::factory()->create();

        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now()->startOfMonth()->addDays(5),
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        $response = $this->actingAs($sales)->get('/reports/sales?period=monthly&year='.now()->year.'&month='.now()->month.'&group=customer');

        $response->assertStatus(200);
        $response->assertSee('テスト顧客');
        $response->assertSee('100,000');
    }

    /**
     * 【テスト目的】 TC-F05: CSVエクスポートが動作する
     * 【テスト内容】 salesロールでGET /reports/sales/exportにアクセスする
     * 【期待される動作】 CSVファイルのダウンロードレスポンスが返る
     * 🔵 REQ-083より
     */
    public function test_csv_export_returns_download_response(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create(['company_name' => 'エクスポート顧客']);
        $product = Product::factory()->create();
        $creator = User::factory()->create();

        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now()->startOfMonth()->addDays(5),
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 12345,
        ]);

        $response = $this->actingAs($sales)->get(
            '/reports/sales/export?period=monthly&year='.now()->year.'&month='.now()->month.'&group=customer'
        );

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
