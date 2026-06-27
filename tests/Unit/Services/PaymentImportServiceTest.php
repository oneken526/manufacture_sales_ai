<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * TASK-0013 単体テスト: PaymentImportService
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0013/payment-import-testcases.md
 */
class PaymentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PaymentImportService
    {
        return $this->app->make(PaymentImportService::class);
    }

    private function createInvoice(int $totalAmount): Invoice
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::ACCOUNTING]);
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::SHIPPED,
            'created_by' => $user->id,
        ]);
        $product = Product::factory()->create(['unit_price' => $totalAmount]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $totalAmount,
        ]);

        $invoiceService = $this->app->make(InvoiceService::class);
        return $invoiceService->issue($order, $user->id);
    }

    /**
     * CSVのヘルパー: 振込データCSV文字列を作成
     */
    private function makeCsvFile(string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bank_csv_');
        file_put_contents($tmp, $content);
        return new UploadedFile($tmp, 'bank.csv', 'text/csv', null, true);
    }

    /**
     * 【テスト目的】 TC-U01: 全銀協フォーマットCSVが正しくパースされ照合成功する
     * 【テスト内容】 請求書番号を含むCSVを処理する
     * 【期待される動作】 paymentsレコード作成・payment_status更新
     * 🔵 REQ-063より
     */
    public function test_csv_import_matches_invoice_by_number(): void
    {
        $invoice = $this->createInvoice(100000);

        $csv = "paid_at,transfer_name,amount,description\n";
        $csv .= "2026-06-27,テスト株式会社,100000,{$invoice->invoice_number}\n";

        $file = $this->makeCsvFile($csv);
        $result = $this->service()->importBankCsv($file, 1);

        $this->assertEquals(1, $result->matchedCount);
        $this->assertEquals(0, $result->unmatchedCount);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'source' => PaymentSource::CSV_IMPORT->value,
        ]);

        $invoice->refresh();
        $this->assertEquals(PaymentStatus::PAID, $invoice->payment_status);
    }

    /**
     * 【テスト目的】 TC-U02: 照合できない行はスキップされ未照合に記録される（EDGE-002）
     * 【テスト内容】 存在しない請求書番号のCSV行を処理する
     * 【期待される動作】 paymentsレコードは作成されず未照合カウント増加
     * 🔵 EDGE-002より
     */
    public function test_unmatched_rows_are_skipped_and_reported(): void
    {
        $csv = "paid_at,transfer_name,amount,description\n";
        $csv .= "2026-06-27,不明会社,50000,INV-2099-9999\n";

        $file = $this->makeCsvFile($csv);
        $result = $this->service()->importBankCsv($file, 1);

        $this->assertEquals(0, $result->matchedCount);
        $this->assertEquals(1, $result->unmatchedCount);
        $this->assertCount(1, $result->unmatchedItems);
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * 【テスト目的】 TC-U03: 部分入金の場合payment_statusがPARTIALLY_PAIDになる
     * 【テスト内容】 total_amount未満の振込データを処理する
     * 【期待される動作】 payment_status=PARTIALLY_PAID(2)に更新
     * 🔵 REQ-062より
     */
    public function test_partial_payment_sets_partially_paid_status(): void
    {
        $invoice = $this->createInvoice(100000);

        $csv = "paid_at,transfer_name,amount,description\n";
        $csv .= "2026-06-27,テスト株式会社,50000,{$invoice->invoice_number}\n";

        $file = $this->makeCsvFile($csv);
        $result = $this->service()->importBankCsv($file, 1);

        $this->assertEquals(1, $result->matchedCount);

        $invoice->refresh();
        $this->assertEquals(PaymentStatus::PARTIALLY_PAID, $invoice->payment_status);
    }
}
