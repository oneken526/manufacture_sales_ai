<?php

namespace App\Services;

use App\DataTransferObjects\CustomerData;
use App\Exceptions\CustomerHasOrdersException;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 顧客マスタの業務ロジックを集約するサービスクラス
 * 🔵 信頼性: architecture.md（Controller → Service → Repository）・TASK-0005.md実装詳細1・4より
 */
class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /**
     * 顧客一覧を取得する。検索キーワードが指定された場合は検索結果を返す
     * 🔵 信頼性: NFR-021（ページネーション1ページ50件） / 🟡 REQ-011（部分一致検索）より
     */
    public function paginate(?string $keyword = null, int $perPage = 50): LengthAwarePaginator
    {
        if ($keyword !== null && $keyword !== '') {
            return $this->customers->search($keyword, $perPage);
        }

        return $this->customers->paginate($perPage);
    }

    public function find(int $id): ?Customer
    {
        return $this->customers->find($id);
    }

    public function create(CustomerData $data): Customer
    {
        return $this->customers->create($data);
    }

    public function update(int $id, CustomerData $data): Customer
    {
        return $this->customers->update($id, $data);
    }

    /**
     * 受注存在チェックを行い、受注がある場合は削除を拒否する
     * 🟡 信頼性: REQ-012「受注が存在する顧客の場合、システムは削除を禁止し警告を表示しなければならない」より
     *
     * @throws CustomerHasOrdersException 受注履歴が存在し削除できない場合
     */
    public function delete(int $customerId): bool
    {
        if ($this->customers->hasOrders($customerId)) {
            throw new CustomerHasOrdersException($customerId);
        }

        return $this->customers->delete($customerId);
    }
}
