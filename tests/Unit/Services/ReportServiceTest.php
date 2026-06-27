<?php

namespace Tests\Unit\Services;

use App\DataTransferObjects\SalesReportData;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0014 単体テスト: ReportService
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0014/report-testcases.md
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 【テスト目的】 TC-U01: aggregateMonthly()が正しい月次集計結果を返す
     * 【テスト内容】 2026年5月に複数件の受注データを用意してaggregateMonthly(2026, 5)を呼び出す
     * 【期待される動作】 totalAmountとrowsが正しく算出される
     * 🔵 REQ-080・data-types.php（SalesReportData）より
     */
    public function test_aggregate_monthly_returns_correct_monthly_results(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $creator = User::factory()->create();

        // 2026-05-10 注文: 3件 × 10,000円 = 30,000円
        $order1 = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => '2026-05-10 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order1->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10000,
        ]);

        // 2026-05-20 注文: 2件 × 5,000円 = 10,000円
        $order2 = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => '2026-05-20 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order2->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 5000,
        ]);

        // 別月（4月）のデータ → 集計対象外
        $orderOther = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => '2026-04-15 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderOther->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
        ]);

        $service = new ReportService();
        $result = $service->aggregateMonthly(2026, 5);

        $this->assertInstanceOf(SalesReportData::class, $result);
        $this->assertEquals('monthly', $result->periodType);
        $this->assertEquals('period', $result->groupBy);
        $this->assertEquals(40000, $result->totalAmount); // 30,000 + 10,000
        $this->assertNotEmpty($result->rows);
    }

    /**
     * 【テスト目的】 TC-U02: 顧客別ランキングが金額降順でソートされる
     * 【テスト内容】 売上金額が異なる複数顧客のデータを用意してrankByCustomerを呼び出す
     * 【期待される動作】 rows配列がamount降順でソートされて返却される
     * 🔵 REQ-081より
     */
    public function test_rank_by_customer_sorted_by_amount_descending(): void
    {
        $customerA = Customer::factory()->create(['company_name' => '顧客A']);
        $customerB = Customer::factory()->create(['company_name' => '顧客B']);
        $customerC = Customer::factory()->create(['company_name' => '顧客C']);
        $creator = User::factory()->create();

        $product = Product::factory()->create(['product_name' => '商品X']);

        // 顧客A: 50,000円（月次集計対象）
        $orderA = SalesOrder::factory()->create([
            'customer_id' => $customerA->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => '2025-03-01 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderA->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
        ]);

        // 顧客B: 20,000円
        $orderB = SalesOrder::factory()->create([
            'customer_id' => $customerB->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => '2025-03-02 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderB->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10000,
        ]);

        // 顧客C: 35,000円
        $orderC = SalesOrder::factory()->create([
            'customer_id' => $customerC->id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => '2025-03-03 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderC->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'unit_price' => 5000,
        ]);

        // キャンセル済み（集計対象外）
        $orderCancelled = SalesOrder::factory()->create([
            'customer_id' => $customerA->id,
            'status' => OrderStatus::CANCELLED,
            'confirmed_at' => '2025-03-04 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderCancelled->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 99999,
        ]);

        $service = new ReportService();

        // 顧客別ランキング（年次、月指定なし）
        $result = $service->rankByCustomer(2025);

        $this->assertInstanceOf(SalesReportData::class, $result);
        $this->assertEquals('customer', $result->groupBy);
        $this->assertCount(3, $result->rows);
        $this->assertEquals(105000, $result->totalAmount); // 50000+35000+20000

        // 金額降順: 顧客A(50000) > 顧客C(35000) > 顧客B(20000)
        $this->assertEquals(50000, $result->rows[0]['amount']);
        $this->assertEquals(35000, $result->rows[1]['amount']);
        $this->assertEquals(20000, $result->rows[2]['amount']);

        // 商品別ランキングも降順であることを確認
        $productA = Product::factory()->create(['product_name' => '製品A']);
        $productB = Product::factory()->create(['product_name' => '製品B']);

        // 製品A: 10*3000 = 30,000円
        $orderProdA = SalesOrder::factory()->create([
            'customer_id' => $customerA->id,
            'status' => OrderStatus::SHIPPED,
            'confirmed_at' => '2025-07-10 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderProdA->id,
            'product_id' => $productA->id,
            'quantity' => 10,
            'unit_price' => 3000,
        ]);

        // 製品B: 2*8000 = 16,000円
        $orderProdB = SalesOrder::factory()->create([
            'customer_id' => $customerB->id,
            'status' => OrderStatus::INVOICED,
            'confirmed_at' => '2025-07-11 09:00:00',
            'created_by' => $creator->id,
        ]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderProdB->id,
            'product_id' => $productB->id,
            'quantity' => 2,
            'unit_price' => 8000,
        ]);

        $productResult = $service->rankByProduct(2025, 7);

        $this->assertEquals('product', $productResult->groupBy);
        $this->assertEquals(46000, $productResult->totalAmount); // 30000+16000
        $this->assertCount(2, $productResult->rows);
        $this->assertEquals(30000, $productResult->rows[0]['amount']); // 製品A
        $this->assertEquals(16000, $productResult->rows[1]['amount']); // 製品B
    }
}
