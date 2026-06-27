<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0012 単体テスト: InvoiceService
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0012/invoice-testcases.md
 */
class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): InvoiceService
    {
        return $this->app->make(InvoiceService::class);
    }

    private function prepareShippedOrder(): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::ACCOUNTING]);
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::SHIPPED,
            'created_by' => $user->id,
        ]);
        $product = Product::factory()->create(['unit_price' => 1000]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 1000,
        ]);
        return ['order' => $order, 'user' => $user];
    }

    /**
     * 【テスト目的】 TC-U01: 同一受注への二重発行が防止される（EDGE-004）
     * 【テスト内容】 既発行の受注に対してissue()を再度呼び出す
     * 【期待される動作】 DuplicateInvoiceExceptionがスローされinvoicesレコードが増えない
     * 🔵 EDGE-004より
     */
    public function test_issue_prevents_duplicate_invoice_for_same_order(): void
    {
        ['order' => $order, 'user' => $user] = $this->prepareShippedOrder();

        $this->service()->issue($order, $user->id);

        $this->expectException(\App\Exceptions\DuplicateInvoiceException::class);

        $this->service()->issue($order, $user->id);

        $this->assertDatabaseCount('invoices', 1);
    }

    /**
     * 【テスト目的】 TC-U02: 採番が年度をまたぐとリセットされる
     * 【テスト内容】 2025年度のsequenceが存在する状態で2026年度の採番を実行する
     * 【期待される動作】 2026年度のsequenceが新規作成されlast_number=1から始まる
     * 🔵 database-schema.sql（document_sequences）より
     */
    public function test_invoice_number_resets_across_fiscal_year(): void
    {
        DocumentSequence::query()->create([
            'document_type' => DocumentType::INVOICE,
            'fiscal_year' => 2025,
            'last_number' => 120,
        ]);

        ['order' => $order, 'user' => $user] = $this->prepareShippedOrder();

        $invoice = $this->service()->issue($order, $user->id);

        $currentYear = (int) now()->format('Y');
        $this->assertStringStartsWith('INV-' . $currentYear . '-', $invoice->invoice_number);
        $this->assertStringEndsWith('0001', $invoice->invoice_number);
    }

    /**
     * 【テスト目的】 TC-U03: 出荷未完了の受注への請求書発行が拒否される
     * 【テスト内容】 status=CONFIRMEDの受注に対してissue()を呼び出す
     * 【期待される動作】 InvalidArgumentExceptionがスローされinvoiceが作成されない
     * 🟡 REQ-060より
     */
    public function test_issue_rejects_non_shipped_order(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::ACCOUNTING]);
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::CONFIRMED,
            'created_by' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->issue($order, $user->id);
    }

    /**
     * 【テスト目的】 TC-U04: 入金ステータスが正しく更新される
     * 【テスト内容】 UNPAIDのinvoiceをPAIDに更新する
     * 【期待される動作】 payment_statusがPAID(3)に更新される
     * 🔵 REQ-062より
     */
    public function test_update_payment_status_changes_invoice_status(): void
    {
        ['order' => $order, 'user' => $user] = $this->prepareShippedOrder();

        $invoice = $this->service()->issue($order, $user->id);
        $this->assertEquals(PaymentStatus::UNPAID, $invoice->payment_status);

        $this->service()->updatePaymentStatus($invoice, PaymentStatus::PAID);

        $invoice->refresh();
        $this->assertEquals(PaymentStatus::PAID, $invoice->payment_status);
    }
}
