<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Exceptions\DuplicateInvoiceException;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 請求書管理コントローラ
 * 🔵 信頼性: api-endpoints.md（請求書管理セクション）・REQ-060〜064より
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {
    }

    /**
     * 請求書一覧（入金ステータスフィルタ対応）
     */
    public function index(Request $request): View
    {
        $query = Invoice::query()
            ->with(['salesOrder.customer', 'issuedBy'])
            ->orderByDesc('issued_at');

        if ($status = $request->query('payment_status')) {
            $query->where('payment_status', (int) $status);
        }

        $invoices = $query->paginate(50)->withQueryString();
        $paymentStatuses = PaymentStatus::cases();

        return view('invoices.index', compact('invoices', 'paymentStatuses'));
    }

    /**
     * 請求書発行
     */
    public function store(Request $request, SalesOrder $order): RedirectResponse
    {
        try {
            $invoice = $this->invoiceService->issue($order, $request->user()->id);

            return redirect()
                ->route('invoices.index')
                ->with('success', sprintf('請求書 %s を発行しました。', $invoice->invoice_number));
        } catch (DuplicateInvoiceException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * 請求書PDFダウンロード
     */
    public function pdf(Invoice $invoice): mixed
    {
        if (! $invoice->invoice_pdf_path || ! file_exists(storage_path('app/' . $invoice->invoice_pdf_path))) {
            return redirect()
                ->back()
                ->with('error', '請求書PDFが見つかりません。');
        }

        return response()->download(
            storage_path('app/' . $invoice->invoice_pdf_path),
            sprintf('invoice_%s.pdf', $invoice->invoice_number)
        );
    }

    /**
     * 入金ステータス手動更新
     */
    public function updatePaymentStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'payment_status' => ['required', 'integer', 'between:1,3'],
        ]);

        $status = PaymentStatus::from((int) $request->input('payment_status'));
        $this->invoiceService->updatePaymentStatus($invoice, $status);

        return redirect()
            ->back()
            ->with('success', '入金ステータスを更新しました。');
    }
}
