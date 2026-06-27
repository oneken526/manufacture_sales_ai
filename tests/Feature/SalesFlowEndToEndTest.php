<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\QuotationService;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * TASK-0016 統合テスト1: 主要業務フロー E2E テスト
 *
 * 見積作成 → 受注確定（在庫引当） → 出荷指示 → 出荷完了（在庫減算）
 * → 請求書発行 → 入金CSV取込 の一連フローを検証する。
 *
 * @see acceptance-criteria.md TC-040-01, TC-043-01, TC-051-01, TC-060-01, TC-063-01
 */
class SalesFlowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $salesUser;

    private User $warehouseUser;

    private User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salesUser = User::factory()->create(['role' => UserRole::SALES, 'is_active' => true]);
        $this->warehouseUser = User::factory()->create(['role' => UserRole::WAREHOUSE, 'is_active' => true]);
        $this->accountingUser = User::factory()->create(['role' => UserRole::ACCOUNTING, 'is_active' => true]);
    }

    /**
     * 【テスト目的】 フルフロー（見積→受注→出荷→請求→入金）が正しく完了する
     * 【テスト内容】 サービス層を直接呼び出して一連の業務フローを実行する
     * 【期待される動作】 各ステップで在庫・ステータス・請求が正しく更新される
     *
     * Covers: TC-040-01, TC-051-01, TC-060-01, TC-063-01
     * 🔵 dataflow.md全体フロー・acceptance-criteria.mdより
     */
    public function test_full_sales_flow_from_quotation_to_payment(): void
    {
        // ─── セットアップ ───
        $customer = Customer::factory()->create(['company_name' => '株式会社テスト']);
        $product = Product::factory()->create([
            'product_name' => 'テスト製品',
            'unit_price' => 10000,
            'stock_quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        // ─── Step1: 見積作成 ───
        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $this->salesUser->id,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
        ]);

        // ─── Step2: 受注確定（在庫引当） ───
        $quotationService = app(QuotationService::class);
        $quotationService->confirmToOrder($quotation);

        $quotation->refresh();
        $product->refresh();
        $this->assertEquals(QuotationStatus::CONVERTED, $quotation->status);
        $this->assertEquals(5, $product->reserved_quantity); // 在庫引当

        $order = $quotation->salesOrder;
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::CONFIRMED, $order->status);

        // ─── Step3: 出荷指示発行 ───
        $orderService = app(OrderService::class);
        $this->actingAs($this->salesUser);
        $orderService->issueShippingInstruction($order);

        $order->refresh();
        $this->assertEquals(OrderStatus::SHIPPING_INSTRUCTED, $order->status);

        // ─── Step4: 出荷完了（在庫減算） ───
        $shipmentService = app(ShipmentService::class);
        $shipmentService->complete($order, $this->warehouseUser->id);

        $order->refresh();
        $product->refresh();
        $this->assertEquals(OrderStatus::SHIPPED, $order->status);
        $this->assertEquals(95, $product->stock_quantity);   // 100 - 5
        $this->assertEquals(0, $product->reserved_quantity); // 引当解除

        // 在庫変動履歴が記録されていること
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => -5,
        ]);

        // ─── Step5: 請求書発行 ───
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->issue($order, $this->accountingUser->id);

        $order->refresh();
        $this->assertEquals(OrderStatus::INVOICED, $order->status);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertEquals(50000, $invoice->total_amount); // 5 * 10000
        $this->assertEquals(PaymentStatus::UNPAID, $invoice->payment_status);

        // ─── Step6: 入金CSVインポート（照合・消込） ───
        $csvContent = "paid_at,transfer_name,amount,description\n2026-06-27,テスト顧客,50000,{$invoice->invoice_number}\n";
        $csvFile = UploadedFile::fake()->createWithContent('payment.csv', $csvContent);

        $response = $this->actingAs($this->accountingUser)
            ->post('/payments/import', ['csv_file' => $csvFile]);

        $response->assertStatus(200);
        $response->assertSee('1件成功');

        $invoice->refresh();
        $this->assertEquals(PaymentStatus::PAID, $invoice->payment_status);
    }

    /**
     * 【テスト目的】 受注キャンセル時に引当在庫が戻ること
     * 【テスト内容】 受注確定後にキャンセルし在庫引当が解除される
     * 【期待される動作】 reserved_quantityが0に戻る
     *
     * Covers: TC-043-01
     * 🔵 REQ-041・OrderService::cancel()より
     */
    public function test_order_cancellation_releases_reserved_stock(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $this->salesUser->id,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 5000,
        ]);

        $quotationService = app(QuotationService::class);
        $quotationService->confirmToOrder($quotation);

        $product->refresh();
        $this->assertEquals(10, $product->reserved_quantity);

        $order = $quotation->salesOrder;
        $orderService = app(OrderService::class);
        $orderService->cancel($order);

        $product->refresh();
        $this->assertEquals(0, $product->reserved_quantity); // 引当解除
        $order->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
    }
}
