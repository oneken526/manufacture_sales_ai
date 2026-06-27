<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\SalesOrder;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status') !== null ? (int) $request->query('status') : null;

        return view('orders.index', [
            'orders' => $this->orderService->paginate($status),
            'currentStatus' => $status,
        ]);
    }

    public function show(SalesOrder $order): View
    {
        $order->load(['customer', 'items.product', 'quotation']);

        return view('orders.show', ['order' => $order]);
    }

    public function edit(SalesOrder $order): View
    {
        return view('orders.edit', ['order' => $order]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $order): RedirectResponse
    {
        $order->update($request->validated());

        return redirect()
            ->route('orders.show', $order)
            ->with('success', '受注情報を更新しました');
    }

    public function cancel(Request $request, SalesOrder $order): RedirectResponse
    {
        try {
            $this->orderService->cancel($order, $request->input('cancel_reason'));

            return redirect()
                ->route('orders.show', $order)
                ->with('success', '受注をキャンセルしました。在庫引当を解除しました。');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', '在庫の整合性エラーが発生しました: '.$e->getMessage());
        }
    }

    public function issueShippingInstruction(SalesOrder $order): RedirectResponse
    {
        try {
            $this->orderService->issueShippingInstruction($order);

            return redirect()
                ->route('orders.show', $order)
                ->with('success', '出荷指示を発行しました。');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }
}
