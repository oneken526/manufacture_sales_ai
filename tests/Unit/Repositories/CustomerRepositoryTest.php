<?php

namespace Tests\Unit\Repositories;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0005 単体テストケース3・4に対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0005.md 単体テスト要件
 */
class CustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CustomerRepositoryInterface
    {
        return $this->app->make(CustomerRepositoryInterface::class);
    }

    /**
     * 【テスト目的】: 会社名・担当者名・電話番号での部分一致検索が正しく機能することを確認する
     * 【テスト内容】: 異なる会社名・担当者名・電話番号を持つ複数の顧客を登録し、search()を呼び出す
     * 【期待される動作】: いずれかの項目にキーワードを部分一致で含む顧客のみが結果に含まれる
     * 🟡 信頼性レベル: TASK-0005.md単体テストケース3（REQ-011）に基づく
     */
    public function test_search_returns_customers_matching_company_name_contact_name_or_phone(): void
    {
        $repository = $this->repository();

        // 【テストデータ準備】: 会社名・担当者名・電話番号がそれぞれ異なる複数の顧客を用意する
        $matchByCompanyName = Customer::factory()->create(['company_name' => '株式会社サンプル製作所', 'contact_name' => '山本 一郎', 'phone' => '03-1111-1111']);
        $matchByContactName = Customer::factory()->create(['company_name' => '別会社株式会社', 'contact_name' => 'サンプル 太郎', 'phone' => '03-2222-2222']);
        $matchByPhone = Customer::factory()->create(['company_name' => '無関係商事', 'contact_name' => '佐々木 花子', 'phone' => '0120-0000-サンプル']);
        $unrelated = Customer::factory()->create(['company_name' => '株式会社別件工業', 'contact_name' => '高橋 次郎', 'phone' => '06-3333-3333']);

        // 【実際の処理実行】: 「サンプル」というキーワードで検索する
        $result = $repository->search('サンプル');
        $ids = $result->getCollection()->pluck('id');

        // 【結果検証】: 会社名・担当者名・電話番号のいずれかに「サンプル」を含む顧客のみが含まれることを確認する
        $this->assertTrue($ids->contains($matchByCompanyName->id)); // 【確認内容】: 会社名に部分一致する顧客が含まれることを確認 🟡
        $this->assertTrue($ids->contains($matchByContactName->id)); // 【確認内容】: 担当者名に部分一致する顧客が含まれることを確認 🟡
        $this->assertTrue($ids->contains($matchByPhone->id)); // 【確認内容】: 電話番号に部分一致する顧客が含まれることを確認 🟡
        $this->assertFalse($ids->contains($unrelated->id)); // 【確認内容】: いずれにも一致しない顧客が結果に含まれないことを確認 🟡
    }

    /**
     * 【テスト目的】: 顧客一覧が1ページ50件単位でページネーションされることを確認する
     * 【テスト内容】: 顧客レコードを60件登録し、paginate()を呼び出す
     * 【期待される動作】: 1ページ目に50件、2ページ目に10件が返却される
     * 🔵 信頼性レベル: TASK-0005.md単体テストケース4（NFR-021）に基づく
     */
    public function test_paginate_returns_fifty_items_per_page(): void
    {
        $repository = $this->repository();

        // 【テストデータ準備】: 顧客レコードを60件登録する
        Customer::factory()->count(60)->create();

        // 【実際の処理実行】: 1ページ目・2ページ目を取得する
        $firstPage = $repository->paginate(50);
        request()->merge(['page' => 2]);
        $secondPage = $repository->paginate(50);

        // 【結果検証】: 1ページ目に50件、2ページ目に10件が含まれ、総件数が60件であることを確認する
        $this->assertCount(50, $firstPage->items()); // 【確認内容】: 1ページ目が50件であることを確認 🔵
        $this->assertCount(10, $secondPage->items()); // 【確認内容】: 2ページ目が10件であることを確認 🔵
        $this->assertSame(60, $firstPage->total()); // 【確認内容】: 総件数が60件であることを確認 🔵
    }
}
