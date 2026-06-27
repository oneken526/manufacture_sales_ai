<?php

namespace Tests\Unit\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0006 製品リポジトリの検索・ページネーションに対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0006.md 完了条件「品番・製品名による部分一致検索ができること（REQ-021）」
 */
class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): ProductRepositoryInterface
    {
        return $this->app->make(ProductRepositoryInterface::class);
    }

    /**
     * 【テスト目的】: 品番・製品名での部分一致検索が正しく機能することを確認する
     * 【テスト内容】: 異なる品番・製品名を持つ複数の製品を登録し、search()を呼び出す
     * 【期待される動作】: 品番または製品名にキーワードを部分一致で含む製品のみが結果に含まれる
     * 🔵 信頼性レベル: TASK-0006.md完了条件「品番・製品名による部分一致検索ができること（REQ-021）」に基づく
     */
    public function test_search_returns_products_matching_product_code_or_product_name(): void
    {
        $repository = $this->repository();

        // 【テストデータ準備】: 品番・製品名がそれぞれ異なる複数の製品を用意する
        $matchByCode = Product::factory()->create(['product_code' => 'SAMPLE-001', 'product_name' => '汎用ボルト']);
        $matchByName = Product::factory()->create(['product_code' => 'P-000999', 'product_name' => 'サンプル金具']);
        $unrelated = Product::factory()->create(['product_code' => 'P-000888', 'product_name' => '六角ナット']);

        // 【実際の処理実行】: 「SAMPLE」というキーワードで検索する
        $result = $repository->search('SAMPLE');
        $ids = $result->getCollection()->pluck('id');

        // 【結果検証】: 品番・製品名のいずれかに部分一致する製品のみが含まれることを確認する
        $this->assertTrue($ids->contains($matchByCode->id)); // 【確認内容】: 品番に部分一致する製品が含まれることを確認 🔵
        $this->assertFalse($ids->contains($unrelated->id)); // 【確認内容】: いずれにも一致しない製品が結果に含まれないことを確認 🔵

        // 【実際の処理実行】: 「サンプル」というキーワードで製品名検索する
        $resultByName = $repository->search('サンプル');
        $idsByName = $resultByName->getCollection()->pluck('id');

        // 【結果検証】: 製品名に部分一致する製品が含まれることを確認する
        $this->assertTrue($idsByName->contains($matchByName->id)); // 【確認内容】: 製品名に部分一致する製品が含まれることを確認 🔵
        $this->assertFalse($idsByName->contains($unrelated->id)); // 【確認内容】: いずれにも一致しない製品が結果に含まれないことを確認 🔵
    }

    /**
     * 【テスト目的】: 製品一覧が1ページ50件単位でページネーションされることを確認する
     * 【テスト内容】: 製品レコードを60件登録し、paginate()を呼び出す
     * 【期待される動作】: 1ページ目に50件、2ページ目に10件が返却される
     * 🔵 信頼性レベル: TASK-0006.md実装詳細2「paginate(50)でページネーション表示する（NFR-021）」に基づく
     */
    public function test_paginate_returns_fifty_items_per_page(): void
    {
        $repository = $this->repository();

        // 【テストデータ準備】: 製品レコードを60件登録する
        Product::factory()->count(60)->create();

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
