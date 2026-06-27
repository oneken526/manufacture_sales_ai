<?php

namespace App\Repositories\Contracts;

use App\DataTransferObjects\CustomerData;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 顧客リポジトリインターフェース
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0005.md実装詳細1より
 */
interface CustomerRepositoryInterface
{
    /**
     * 顧客一覧をページネーション付きで取得する
     */
    public function paginate(int $perPage = 50): LengthAwarePaginator;

    /**
     * 主キーで顧客を取得する（存在しない場合はnull）
     */
    public function find(int $id): ?Customer;

    /**
     * 新規顧客を登録する
     */
    public function create(CustomerData $data): Customer;

    /**
     * 顧客情報を更新する
     */
    public function update(int $id, CustomerData $data): Customer;

    /**
     * 顧客を論理削除する
     */
    public function delete(int $id): bool;

    /**
     * 会社名・担当者名・電話番号の部分一致検索を行う
     *
     * 検索結果はページネーション付きで返却する
     */
    public function search(string $keyword, int $perPage = 50): LengthAwarePaginator;

    /**
     * 当該顧客に紐づく受注が存在するかどうかを判定する
     */
    public function hasOrders(int $customerId): bool;
}
