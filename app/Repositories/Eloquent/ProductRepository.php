<?php

namespace App\Repositories\Eloquent;

use App\DataTransferObjects\ProductData;
use App\Exceptions\StockAdjustmentViolatesIntegrityException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 製品リポジトリのEloquent実装
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0006.md実装詳細1・3より
 */
class ProductRepository implements ProductRepositoryInterface
{
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return Product::query()
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Product
    {
        return Product::query()->find($id);
    }

    public function create(ProductData $data): Product
    {
        return Product::query()->create($data->toArray());
    }

    public function update(int $id, ProductData $data): Product
    {
        $product = Product::query()->findOrFail($id);
        $product->update($data->toArray());

        return $product->refresh();
    }

    /**
     * 品番・製品名に対する部分一致（LIKE）OR検索を行う
     * 🔵 信頼性: REQ-021・NFR-013（クエリビルダ使用によるSQLインジェクション対策）より
     */
    public function search(string $keyword, int $perPage = 50): LengthAwarePaginator
    {
        return Product::query()
            ->where(function ($query) use ($keyword) {
                $query->where('product_code', 'like', "%{$keyword}%")
                    ->orWhere('product_name', 'like', "%{$keyword}%");
            })
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * 在庫数を増減し、stock_movementsへ変動履歴を記録する
     *
     * 行ロック（lockForUpdate）を取得した上で在庫整合性（stock_quantity >= 0、
     * reserved_quantity <= stock_quantity）を検証し、在庫更新と履歴記録を
     * 単一のDBトランザクション内でアトミックに実行する。
     * 🔵 信頼性: TASK-0006.md実装詳細3・注意事項（トランザクション内での一貫した更新）より
     *
     * @throws StockAdjustmentViolatesIntegrityException 調整結果が在庫整合性制約に違反する場合
     */
    public function adjustStock(Product $product, int $quantityChange, int $reason, int $operatedBy, ?string $memo): Product
    {
        return DB::transaction(function () use ($product, $quantityChange, $reason, $operatedBy, $memo) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $newStockQuantity = $locked->stock_quantity + $quantityChange;

            if ($newStockQuantity < 0 || $newStockQuantity < $locked->reserved_quantity) {
                throw new StockAdjustmentViolatesIntegrityException(
                    $locked->id,
                    '調整後の在庫数が引当中数量を下回るため実行できません'
                );
            }

            $locked->update(['stock_quantity' => $newStockQuantity]);

            StockMovement::query()->create([
                'product_id' => $locked->id,
                'reason' => $reason,
                'quantity_change' => $quantityChange,
                'related_order_id' => null,
                'operated_by' => $operatedBy,
                'memo' => $memo,
                'created_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}
