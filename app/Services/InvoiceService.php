<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\DuplicateInvoiceException;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * 請求書管理サービス
 * 🔵 信頼性: dataflow.md機能3・REQ-060〜064・EDGE-004より
 */
class InvoiceService
{
    /**
     * 請求書を発行する。
     *
     * - 出荷完了済み（SHIPPED）の受注のみ対象
     * - 同一受注への二重発行を防止（EDGE-004）
     * - document_sequencesで年度+連番採番
     *
     * @throws \InvalidArgumentException 出荷完了済み以外の受注の場合
     * @throws DuplicateInvoiceException 既に発行済みの場合
     */
    public function issue(SalesOrder $order, int $userId): Invoice
    {
        $existing = Invoice::query()->where('sales_order_id', $order->id)->first();
        if ($existing !== null) {
            throw new DuplicateInvoiceException();
        }

        if ($order->status !== OrderStatus::SHIPPED) {
            throw new \InvalidArgumentException(
                sprintf('ステータス「%s」の受注は請求書を発行できません。出荷完了後に発行してください。', $order->status->label())
            );
        }

        return DB::transaction(function () use ($order, $userId) {
            $fiscalYear = (int) now()->format('Y');
            $nextNumber = DocumentSequence::issueNextNumber(DocumentType::INVOICE, $fiscalYear);
            $invoiceNumber = sprintf('INV-%d-%04d', $fiscalYear, $nextNumber);

            $order->load('items');
            $totalAmount = $order->items->sum(fn ($item) => $item->quantity * $item->unit_price);

            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'sales_order_id' => $order->id,
                'total_amount' => $totalAmount,
                'payment_status' => PaymentStatus::UNPAID,
                'issued_at' => now(),
                'issued_by' => $userId,
            ]);

            $order->update(['status' => OrderStatus::INVOICED]);

            return $invoice;
        });
    }

    /**
     * 入金ステータスを更新する。
     */
    public function updatePaymentStatus(Invoice $invoice, PaymentStatus $status): void
    {
        $invoice->update(['payment_status' => $status]);
    }
}
