<?php

namespace App\Services;

use App\DataTransferObjects\PaymentImportResultData;
use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * 振込データCSVインポートサービス
 * 🔵 信頼性: dataflow.md機能4・REQ-063・EDGE-002より
 */
class PaymentImportService
{
    /**
     * 全銀協フォーマットCSVをインポートし請求書と照合する。
     *
     * CSV形式（1行目はヘッダ）:
     *   paid_at, transfer_name, amount, description
     *
     * 照合ロジック:
     *   1. descriptionから請求書番号（INV-YYYY-NNNN形式）を抽出して検索
     *   2. 見つからない場合はamountが一致する未入金請求書を1件検索（フォールバック）
     *
     * @return PaymentImportResultData 照合結果サマリー
     */
    public function importBankCsv(UploadedFile $file, int $operatedBy): PaymentImportResultData
    {
        $lines = array_filter(
            explode("\n", str_replace("\r", '', $file->get())),
            fn ($l) => trim($l) !== ''
        );

        $matchedCount = 0;
        $unmatchedCount = 0;
        $unmatchedItems = [];

        foreach (array_slice(array_values($lines), 1) as $line) {
            $columns = str_getcsv($line);
            if (count($columns) < 4) {
                continue;
            }

            [$paidAt, $transferName, $amountRaw, $description] = $columns;
            $amount = (int) str_replace([',', '，'], '', $amountRaw);

            $invoice = $this->findInvoice($description, $amount);

            if ($invoice === null) {
                $unmatchedCount++;
                $unmatchedItems[] = [
                    'row' => $line,
                    'reason' => '該当する請求書が見つかりません',
                ];
                continue;
            }

            DB::transaction(function () use ($invoice, $amount, $paidAt, $line) {
                Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'source' => PaymentSource::CSV_IMPORT,
                    'raw_csv_row' => $line,
                ]);

                $this->updatePaymentStatus($invoice);
            });

            $matchedCount++;
        }

        return new PaymentImportResultData($matchedCount, $unmatchedCount, $unmatchedItems);
    }

    private function findInvoice(string $description, int $amount): ?Invoice
    {
        // 請求書番号パターン（例: INV-2026-0001）を検索
        if (preg_match('/INV-\d{4}-\d{4,}/', $description, $matches)) {
            $invoice = Invoice::query()->where('invoice_number', $matches[0])->first();
            if ($invoice !== null) {
                return $invoice;
            }
        }

        // フォールバック: 同一金額の未入金請求書を検索
        return Invoice::query()
            ->where('total_amount', $amount)
            ->where('payment_status', PaymentStatus::UNPAID)
            ->first();
    }

    private function updatePaymentStatus(Invoice $invoice): void
    {
        $paidTotal = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount');

        $status = match (true) {
            $paidTotal <= 0 => PaymentStatus::UNPAID,
            $paidTotal < $invoice->total_amount => PaymentStatus::PARTIALLY_PAID,
            default => PaymentStatus::PAID,
        };

        $invoice->update(['payment_status' => $status]);
    }
}
