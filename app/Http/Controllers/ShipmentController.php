<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 出荷管理コントローラ
 * 🔵 信頼性: api-endpoints.md（出荷管理セクション）・REQ-050〜053より
 */
class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentService $shipmentService,
    ) {
    }

    /**
     * 出荷指示一覧（status=SHIPPING_INSTRUCTED の受注を表示）
     */
    public function index(Request $request): View
    {
        $query = SalesOrder::query()
            ->with(['customer'])
            ->where('status', \App\Enums\OrderStatus::SHIPPING_INSTRUCTED->value)
            ->orderByDesc('confirmed_at');

        if ($keyword = $request->query('q')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('order_number', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('company_name', 'like', "%{$keyword}%"));
            });
        }

        $orders = $query->paginate(50)->withQueryString();

        return view('shipments.index', compact('orders'));
    }

    /**
     * 出荷完了登録
     */
    public function complete(Request $request, SalesOrder $order): RedirectResponse
    {
        try {
            $shipment = $this->shipmentService->complete($order, $request->user()->id);

            return redirect()
                ->route('shipments.index')
                ->with('success', sprintf('受注 %s の出荷完了を登録しました。', $order->order_number));
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * 納品書PDFダウンロード
     */
    public function deliveryNote(Shipment $shipment): mixed
    {
        if (! $shipment->delivery_note_path || ! file_exists(storage_path('app/' . $shipment->delivery_note_path))) {
            return redirect()
                ->back()
                ->with('error', '納品書PDFが見つかりません。');
        }

        return response()->download(
            storage_path('app/' . $shipment->delivery_note_path),
            sprintf('delivery_note_%s.pdf', $shipment->salesOrder->order_number ?? $shipment->id)
        );
    }

    /**
     * 返品登録
     */
    public function processReturn(Request $request, Shipment $shipment): RedirectResponse
    {
        $request->validate([
            'return_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->shipmentService->processReturn(
                $shipment,
                $request->input('return_reason'),
                $request->user()->id
            );

            return redirect()
                ->route('shipments.index')
                ->with('success', '返品登録が完了しました。在庫を加算しました。');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
