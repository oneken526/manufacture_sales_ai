<?php

namespace App\Services;

use App\DataTransferObjects\ProductData;
use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * 製品マスタの業務ロジックを集約するサービスクラス
 * 🔵 信頼性: architecture.md（Controller → Service → Repository）・TASK-0006.md実装詳細1・3・4より
 */
class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * 製品一覧を取得する。検索キーワードが指定された場合は検索結果を返す
     * 🔵 信頼性: NFR-021（ページネーション1ページ50件） / 🔵 REQ-021（部分一致検索）より
     */
    public function paginate(?string $keyword = null, int $perPage = 50): LengthAwarePaginator
    {
        if ($keyword !== null && $keyword !== '') {
            return $this->products->search($keyword, $perPage);
        }

        return $this->products->paginate($perPage);
    }

    public function find(int $id): ?Product
    {
        return $this->products->find($id);
    }

    public function create(ProductData $data): Product
    {
        return $this->products->create($data);
    }

    public function update(int $id, ProductData $data): Product
    {
        return $this->products->update($id, $data);
    }

    /**
     * 利用可能在庫数（実在庫 - 引当中在庫）を計算する
     * 🔵 信頼性: data-types.php（ProductData::availableQuantity() = stockQuantity - reservedQuantity）より
     */
    public function availableQuantity(Product $product): int
    {
        return $product->stock_quantity - $product->reserved_quantity;
    }

    /**
     * 在庫数がアラート閾値を下回っているかどうかを判定する
     * 🟡 信頼性: REQ-022「在庫数が設定した閾値を下回った場合、システムは警告を表示しなければならない」より
     */
    public function isLowStock(Product $product): bool
    {
        return $product->stock_quantity < $product->alert_threshold;
    }

    /**
     * 在庫数を手動調整し、変動履歴をstock_movementsへ記録する（reason=5 manual_adjustment）
     * 🔵 信頼性: REQ-023「製品の在庫数を直接増減できなければならない」、REQ-072「在庫変動履歴を記録」、
     *           database-schema.sql（chk_products_reserved_le_stock制約）、TASK-0006.md実装詳細3より
     *
     * 在庫更新と変動履歴の記録はProductRepository::adjustStock()内のDBトランザクションで
     * アトミックに実行され、整合性違反時はStockAdjustmentViolatesIntegrityExceptionがスローされる。
     *
     * @return int 調整後の在庫数
     *
     * @throws ModelNotFoundException 指定IDの製品が存在しない場合
     */
    public function adjustStock(int $productId, int $quantityChange, int $operatedBy, ?string $memo = null): int
    {
        $product = $this->products->find($productId);

        if ($product === null) {
            throw new ModelNotFoundException("Product [{$productId}] not found");
        }

        $updated = $this->products->adjustStock(
            $product,
            $quantityChange,
            StockMovementReason::MANUAL_ADJUSTMENT->value,
            $operatedBy,
            $memo,
        );

        return $updated->stock_quantity;
    }
}
