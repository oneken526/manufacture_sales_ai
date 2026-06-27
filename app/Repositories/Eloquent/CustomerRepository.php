<?php

namespace App\Repositories\Eloquent;

use App\DataTransferObjects\CustomerData;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 顧客リポジトリのEloquent実装
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0005.md実装詳細1・3より
 */
class CustomerRepository implements CustomerRepositoryInterface
{
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return Customer::query()
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Customer
    {
        return Customer::query()->find($id);
    }

    public function create(CustomerData $data): Customer
    {
        return Customer::query()->create($data->toArray());
    }

    public function update(int $id, CustomerData $data): Customer
    {
        $customer = Customer::query()->findOrFail($id);
        $customer->update($data->toArray());

        return $customer->refresh();
    }

    public function delete(int $id): bool
    {
        $customer = Customer::query()->findOrFail($id);

        return (bool) $customer->delete();
    }

    /**
     * 会社名・担当者名・電話番号に対する部分一致（LIKE）OR検索を行う
     * 🟡 信頼性: REQ-011・NFR-013（クエリビルダ使用によるSQLインジェクション対策）より
     */
    public function search(string $keyword, int $perPage = 50): LengthAwarePaginator
    {
        return Customer::query()
            ->where(function ($query) use ($keyword) {
                $query->where('company_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            })
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * sales_orders.customer_id に対する存在確認のみを行い、N+1クエリを避ける
     * 🟡 信頼性: TASK-0005.md注意事項「idx_sales_orders_customer_idを活用すること」より
     */
    public function hasOrders(int $customerId): bool
    {
        return Customer::query()
            ->whereKey($customerId)
            ->whereHas('salesOrders')
            ->exists();
    }
}
