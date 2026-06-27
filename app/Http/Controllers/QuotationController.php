<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\QuotationData;
use App\Enums\QuotationStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreQuotationRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\PdfService;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * 見積管理コントローラ
 * 🔵 信頼性: TASK-0008.md実装詳細6（QuotationController: index/create/store/show/pdf/confirm）より
 *
 * ロールベースアクセス制御はルート定義側の`role:`ミドルウェアに委譲する。
 */
class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly PdfService $pdfService,
    ) {
    }

    public function index(): View
    {
        return view('quotations.index', [
            'quotations' => $this->quotationService->paginate(),
        ]);
    }

    /**
     * 🔵 信頼性: REQ-030（見積の作成）より
     */
    public function create(): View
    {
        return view('quotations.create', [
            'customers' => Customer::query()->orderBy('company_name')->get(),
            'products' => Product::query()->orderBy('product_name')->get(),
        ]);
    }

    /**
     * 見積明細行ごとの金額・合計金額をリアルタイムに再計算する内部AJAXエンドポイント
     * 🟡 信頼性: api-endpoints.md「POST /api/internal/quotations/calculate」・TASK-0008.md実装詳細8より
     */
    public function calculate(Request $request): JsonResponse
    {
        $items = collect($request->input('items', []))
            ->map(function (array $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $unitPrice = (int) ($item['unit_price'] ?? 0);

                return [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $quantity * $unitPrice,
                ];
            })
            ->values();

        return response()->json([
            'items' => $items,
            'total' => $items->sum('amount'),
        ]);
    }

    /**
     * 🔵 信頼性: REQ-030（見積の作成）・TASK-0008.md実装詳細2（見積番号採番）より
     */
    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $quotation = $this->quotationService->create(
            QuotationData::fromArray($request->validated(), $request->user()->id)
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('status', '見積を作成しました');
    }

    /**
     * 見積詳細（明細・合計金額・受注確定操作を含む）
     * 🔵 信頼性: REQ-030・TASK-0008.md完了条件「QuotationControllerにより...詳細...が動作すること」より
     */
    public function show(Quotation $quotation): View
    {
        $quotation->load(['customer', 'items.product', 'salesOrder']);

        return view('quotations.show', [
            'quotation' => $quotation,
        ]);
    }

    /**
     * 見積PDFのプレビュー・ダウンロード
     * 🔵 信頼性: REQ-032・TASK-0008.md実装詳細4（PdfService・QuotationPdfテンプレート）より
     */
    public function pdf(Quotation $quotation): Response
    {
        $quotation->load(['customer', 'items.product']);

        return $this->pdfService->download(
            'pdf.quotations.show',
            ['quotation' => $quotation],
            $quotation->quotation_number.'.pdf'
        );
    }

    /**
     * 見積から受注への転換を実行する
     *
     * 有効期限切れの見積は確定不可とし（REQ-033）、自動的にステータスを期限切れへ更新した上で
     * 警告メッセージを表示する。在庫不足時はInsufficientStockExceptionを捕捉し、
     * 不足している製品・要求数量・利用可能数量を含む警告メッセージを表示する（EDGE-001）。
     * 🔵 信頼性レベル: TASK-0008.md統合テスト1・2・実装詳細6・7・REQ-033・EDGE-001より
     */
    public function confirm(Quotation $quotation): RedirectResponse
    {
        if ($quotation->expires_at !== null && $quotation->expires_at->isPast()) {
            if ($quotation->status !== QuotationStatus::EXPIRED) {
                $quotation->update(['status' => QuotationStatus::EXPIRED]);
            }

            return redirect()
                ->route('quotations.show', $quotation)
                ->with('warning', 'この見積は有効期限が切れているため受注確定できません。再見積を作成してください。');
        }

        try {
            $this->quotationService->confirmToOrder($quotation);
        } catch (InsufficientStockException $e) {
            return redirect()
                ->route('quotations.show', $quotation)
                ->with('warning', sprintf(
                    '在庫が不足しています（製品ID: %d、要求数量: %d、利用可能数量: %d）。受注確定を中止しました。',
                    $e->productId,
                    $e->requestedQuantity,
                    $e->availableQuantity,
                ));
        }

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '受注を確定しました');
    }
}
