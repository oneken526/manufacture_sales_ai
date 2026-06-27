<?php

namespace Tests\Feature\Products;

use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0006 統合テスト1・2に対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0006.md 統合テスト要件
 */
class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】: 製品登録→検索→在庫調整→変動履歴記録の一連フローがエラーなく完了することを確認する
     * 【テスト内容】: admin権限で製品登録、一覧画面で品番検索、warehouse権限で在庫調整を行う
     * 【期待される動作】: 各操作が正しく完了し、stock_quantityとstock_movementsの整合性が保たれる
     * 🔵 信頼性レベル: TASK-0006.md統合テスト1（REQ-020, REQ-023, REQ-072）に直接基づく
     */
    public function test_product_can_be_registered_searched_and_stock_adjusted_with_movement_recorded(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        // 【実際の処理実行】: admin権限のユーザーで製品登録フォームから新規製品を登録する
        $storeResponse = $this->actingAs($admin)->post(route('products.store'), [
            'product_code' => 'INT-TEST-001',
            'product_name' => '統合テスト製品',
            'unit_price' => 5_000,
            'unit' => '個',
            'stock_quantity' => 100,
            'alert_threshold' => 10,
        ]);

        $product = Product::where('product_code', 'INT-TEST-001')->firstOrFail();
        $storeResponse->assertRedirect(route('products.edit', $product)); // 【確認内容】: 登録後に編集画面へリダイレクトされることを確認 🔵
        $this->assertDatabaseHas('products', [
            'product_code' => 'INT-TEST-001',
            'stock_quantity' => 100,
        ]); // 【確認内容】: 入力内容がDBに登録されることを確認 🔵

        // 【実際の処理実行】: 製品一覧画面で品番検索を行う
        $searchResponse = $this->actingAs($admin)->get(route('products.index', ['q' => 'INT-TEST']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('統合テスト製品'); // 【確認内容】: 検索結果に対象製品が表示されることを確認 🔵

        // 【実際の処理実行】: warehouse権限のユーザーで在庫調整画面から在庫数を増減し、メモを入力して送信する
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $adjustResponse = $this->actingAs($warehouse)->post(route('products.adjust-stock', $product), [
            'quantity_change' => -20,
            'memo' => '棚卸しによる差異調整',
        ]);
        $adjustResponse->assertRedirect(route('products.edit', $product)); // 【確認内容】: 調整後に編集画面へリダイレクトされることを確認 🔵

        // 【結果検証】: 製品のstock_quantityが更新され、stock_movementsに手動調整(reason=5)の履歴が記録されていることを確認する
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 80,
        ]); // 【確認内容】: 在庫調整結果がDBに反映されることを確認 🔵
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovementReason::MANUAL_ADJUSTMENT->value,
            'quantity_change' => -20,
            'operated_by' => $warehouse->id,
            'memo' => '棚卸しによる差異調整',
        ]); // 【確認内容】: 在庫変動履歴がreason=手動調整として記録されることを確認 🔵
    }

    /**
     * 【テスト目的】: 在庫アラート対象の製品が一覧画面で視覚的に区別されることを確認する
     * 【テスト内容】: alert_thresholdを上回る製品と下回る製品を用意し、一覧画面を表示する
     * 【期待される動作】: 閾値を下回る製品にのみ警告バッジ（在庫不足）が表示される
     * 🟡 信頼性レベル: TASK-0006.md統合テスト2（REQ-022）に基づく
     */
    public function test_products_below_alert_threshold_are_visually_marked_in_index(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        // 【テストデータ準備】: 閾値を上回る在庫数の製品と下回る在庫数の製品を用意する
        $sufficientStock = Product::factory()->create([
            'product_code' => 'OK-001',
            'product_name' => '在庫十分製品',
            'stock_quantity' => 100,
            'alert_threshold' => 10,
        ]);
        $lowStock = Product::factory()->create([
            'product_code' => 'NG-001',
            'product_name' => '在庫不足製品',
            'stock_quantity' => 5,
            'alert_threshold' => 10,
        ]);

        // 【実際の処理実行】: 製品一覧画面を表示する
        $response = $this->actingAs($admin)->get(route('products.index'));
        $response->assertOk();

        // 【結果検証】: 閾値を下回る製品にのみ警告バッジが表示されることを確認する
        $response->assertSee('在庫十分製品'); // 【確認内容】: 在庫十分の製品が一覧に表示されることを確認 🟡
        $response->assertSee('在庫不足製品'); // 【確認内容】: 在庫不足の製品が一覧に表示されることを確認 🟡

        // 【結果検証】: 警告バッジ「在庫不足」が「在庫不足製品」の行内（製品名より後ろ）にのみ出現し、
        // 「在庫十分製品」の行には出現しないことを確認する
        $content = $response->getContent();
        $lowStockRowPos = mb_strpos($content, '在庫不足製品');
        $this->assertNotFalse($lowStockRowPos); // 【確認内容】: 在庫不足製品の行が存在することを確認 🟡

        // 「在庫不足製品」という製品名自体に「在庫不足」が含まれるため、製品名より後ろの位置から検索する
        $badgePos = mb_strpos($content, '在庫不足', $lowStockRowPos + mb_strlen('在庫不足製品'));
        $this->assertNotFalse($badgePos); // 【確認内容】: 在庫不足製品の行内に警告バッジが表示されることを確認 🟡

        // 「在庫十分製品」の行（badgePosより前の範囲）には警告バッジが含まれないことを確認する
        $sufficientRowSegment = mb_substr($content, 0, $badgePos);
        $this->assertStringNotContainsString('在庫不足', str_replace('在庫不足製品', '', $sufficientRowSegment)); // 【確認内容】: 在庫十分製品には警告バッジが付与されないことを確認 🟡
    }
}
