<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 在庫管理コントローラ（閲覧専用）
 * 🔵 信頼性: api-endpoints.md（GET /inventory, GET /inventory/{product}/movements）・REQ-070, REQ-072より
 */
class InventoryController extends Controller
{
    /**
     * 在庫一覧（現在庫・引当中・利用可能数・アラート表示）
     */
    public function index(Request $request): View
    {
        $query = Product::query()->orderBy('product_code');

        if ($keyword = $request->query('q')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('product_code', 'like', "%{$keyword}%")
                    ->orWhere('product_name', 'like', "%{$keyword}%");
            });
        }

        $products = $query->paginate(50)->withQueryString();

        $alertCount = Product::query()
            ->whereColumn('stock_quantity', '<=', 'alert_threshold')
            ->count();

        return view('inventory.index', compact('products', 'alertCount'));
    }

    /**
     * 製品別在庫変動履歴（日時降順・フィルタ対応）
     */
    public function movements(Request $request, Product $product): View
    {
        $query = StockMovement::query()
            ->with(['operator', 'relatedOrder'])
            ->where('product_id', $product->id)
            ->orderByDesc('created_at');

        if ($reason = $request->query('reason')) {
            $query->where('reason', (int) $reason);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $movements = $query->paginate(50)->withQueryString();
        $reasons = StockMovementReason::cases();

        return view('inventory.movements', compact('product', 'movements', 'reasons'));
    }
}
