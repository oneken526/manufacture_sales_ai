<?php

namespace App\Repositories\Contracts;

use App\Models\SalesOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受注リポジトリインターフェース
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0009.md実装詳細1より
 */
interface SalesOrderRepositoryInterface
{
    /**
     * 受注一覧をページネーション付きで取得する（ステータスフィルタ対応）
     * 🔵 信頼性: TASK-0009.md実装詳細2「index(): ステータスフィルタとページネーション50件」より
     */
    public function paginate(?int $status = null, int $perPage = 50): LengthAwarePaginator;

    /**
     * 主キーで受注を取得する（存在しない場合はnull）
     */
    public function find(int $id): ?SalesOrder;
}
