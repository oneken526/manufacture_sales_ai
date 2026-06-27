<?php

namespace App\Repositories\Eloquent;

use App\Models\SalesOrder;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受注リポジトリのEloquent実装
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0009.md実装詳細1より
 */
class EloquentSalesOrderRepository implements SalesOrderRepositoryInterface
{
    /**
     * 受注一覧をページネーション付きで取得する
     *
     * ステータスが指定された場合は絞り込みを行う（NFR-021: 50件/ページ）。
     * 🔵 信頼性: TASK-0009.md実装詳細2「index(): ステータスフィルタ（status）とページネーション50件」より
     */
    public function paginate(?int $status = null, int $perPage = 50): LengthAwarePaginator
    {
        return SalesOrder::query()
            ->with(['customer'])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * 主キーで受注を取得する
     */
    public function find(int $id): ?SalesOrder
    {
        return SalesOrder::query()->find($id);
    }
}
