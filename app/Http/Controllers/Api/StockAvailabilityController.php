<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 利用可能在庫チェックAPI
 * 🔵 信頼性: api-endpoints.md「GET /api/internal/products/{product}/availability」・EDGE-001, EDGE-010より
 *
 * レスポンス形式: { "success": true, "data": { "productId", "stockQuantity",
 *   "reservedQuantity", "availableQuantity", "sufficient" } }
 */
class StockAvailabilityController extends Controller
{
    /**
     * 指定製品の利用可能在庫を返す。
     *
     * @param  Request  $request  ?quantity={n} 確認したい数量
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $quantity = (int) $request->query('quantity', 1);
        $available = $product->stock_quantity - $product->reserved_quantity;

        return response()->json([
            'success' => true,
            'data' => [
                'productId' => $product->id,
                'stockQuantity' => $product->stock_quantity,
                'reservedQuantity' => $product->reserved_quantity,
                'availableQuantity' => $available,
                'sufficient' => $available >= $quantity,
            ],
        ]);
    }
}
