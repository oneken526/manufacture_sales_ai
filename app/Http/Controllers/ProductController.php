<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\ProductData;
use App\Exceptions\StockAdjustmentViolatesIntegrityException;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 製品マスタ管理コントローラ
 * 🔵 信頼性: api-endpoints.md（製品管理エンドポイント群）・TASK-0006.md実装詳細2・3より
 *
 * ロールベースアクセス制御はルート定義側の`role:`ミドルウェアに委譲する。
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    /**
     * jQueryインクリメンタルサーチ用の内部AJAXエンドポイント
     * 🔵 信頼性: api-endpoints.md「GET /api/internal/products/search?q={keyword}」・REQ-021より
     */
    public function searchJson(Request $request): JsonResponse
    {
        $keyword = $request->string('q')->toString();

        $products = $keyword !== ''
            ? $this->productService->paginate($keyword, 20)
            : collect();

        $collection = $products instanceof \Illuminate\Pagination\LengthAwarePaginator
            ? $products->getCollection()
            : $products;

        return response()->json([
            'data' => $collection->map(fn (Product $product) => [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'unit_price' => $product->unit_price,
                'available_quantity' => $product->stock_quantity - $product->reserved_quantity,
            ])->values(),
        ]);
    }

    /**
     * 製品一覧（検索・在庫アラート表示）
     * 🔵 信頼性: NFR-021（ページネーション1ページ50件）・🔵 REQ-021（部分一致検索）・🟡 REQ-022（在庫アラート表示）より
     */
    public function index(Request $request): View
    {
        $keyword = $request->string('q')->toString();
        $products = $this->productService->paginate($keyword !== '' ? $keyword : null);

        return view('products.index', [
            'products' => $products,
            'keyword' => $keyword,
        ]);
    }

    public function create(): View
    {
        return view('products.create');
    }

    /**
     * 🔵 信頼性: REQ-020（製品情報の登録）より
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = $this->productService->create(
            ProductData::fromArray($request->validated())
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('status', '製品を登録しました');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
        ]);
    }

    /**
     * 🔵 信頼性: REQ-020（製品情報の編集）より
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->productService->update(
            $product->id,
            ProductData::fromArray($request->validated(), $product->id)
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('status', '製品情報を更新しました');
    }

    /**
     * 在庫手動調整フォーム表示
     * 🟡 信頼性: TASK-0006.md UI/UX要件「在庫調整フォーム」より
     */
    public function adjustStockForm(Product $product): View
    {
        return view('products.adjust-stock', [
            'product' => $product,
        ]);
    }

    /**
     * 在庫手動調整（reason=5 manual_adjustment としてstock_movementsへ記録）
     * 🔵 信頼性: REQ-023, REQ-072・database-schema.sql（stock_movements.reason=5）・TASK-0006.md実装詳細3より
     */
    public function adjustStock(AdjustStockRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->productService->adjustStock(
                $product->id,
                (int) $request->validated('quantity_change'),
                $request->user()->id,
                $request->validated('memo'),
            );
        } catch (StockAdjustmentViolatesIntegrityException $e) {
            return redirect()
                ->route('products.adjust-stock.form', $product)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('products.edit', $product)
            ->with('status', '在庫数を調整しました');
    }
}
