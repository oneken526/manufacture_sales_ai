<?php

namespace App\Repositories\Contracts;

use App\DataTransferObjects\ProductData;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 製品リポジトリインターフェース
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0006.md実装詳細1より
 */
interface ProductRepositoryInterface
{
    /**
     * 製品一覧をページネーション付きで取得する
     */
    public function paginate(int $perPage = 50): LengthAwarePaginator;

    /**
     * 主キーで製品を取得する（存在しない場合はnull）
     */
    public function find(int $id): ?Product;

    /**
     * 新規製品を登録する
     */
    public function create(ProductData $data): Product;

    /**
     * 製品情報を更新する
     */
    public function update(int $id, ProductData $data): Product;

    /**
     * 品番・製品名の部分一致検索を行う
     *
     * 検索結果はページネーション付きで返却する
     */
    public function search(string $keyword, int $perPage = 50): LengthAwarePaginator;

    /**
     * 在庫数を増減し、変動履歴を記録する
     *
     * 在庫数の更新と変動履歴の記録をトランザクション内でアトミックに実行する
     */
    public function adjustStock(Product $product, int $quantityChange, int $reason, int $operatedBy, ?string $memo): Product;
}
