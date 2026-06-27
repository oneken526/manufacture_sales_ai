<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0015 統合テスト: 内部API（検索・在庫チェック・見積計算）
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0015/internal-api-testcases.md
 */
class InternalApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role = UserRole::SALES): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    // =========================================================
    // 利用可能在庫チェック API
    // =========================================================

    /**
     * 【テスト目的】 TC-U01: 在庫チェックAPIが充足/不足を正しく判定する
     * 【テスト内容】 実在庫50・引当中20（利用可能30）の製品に対してquantity=10とquantity=50で呼び出す
     * 【期待される動作】 10はsufficient:true、50はsufficient:false
     * 🔵 EDGE-001, EDGE-010より
     */
    public function test_stock_availability_returns_sufficient_and_insufficient(): void
    {
        $user = $this->user();
        $product = Product::factory()->create([
            'stock_quantity' => 50,
            'reserved_quantity' => 20,
        ]);

        // 充足ケース: 利用可能30 >= 要求10
        $response = $this->actingAs($user)->get(
            "/api/internal/products/{$product->id}/availability?quantity=10"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'productId' => $product->id,
                    'stockQuantity' => 50,
                    'reservedQuantity' => 20,
                    'availableQuantity' => 30,
                    'sufficient' => true,
                ],
            ]);

        // 不足ケース: 利用可能30 < 要求50
        $response2 = $this->actingAs($user)->get(
            "/api/internal/products/{$product->id}/availability?quantity=50"
        );

        $response2->assertStatus(200)
            ->assertJsonFragment(['sufficient' => false]);
    }

    /**
     * 【テスト目的】 TC-U02: 在庫が0の製品は常に不足と判定される
     * 【テスト内容】 実在庫0の製品に対してquantity=1で呼び出す
     * 【期待される動作】 availableQuantity:0, sufficient:false
     * 🔵 EDGE-010より
     */
    public function test_stock_availability_with_zero_stock_returns_insufficient(): void
    {
        $user = $this->user();
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(
            "/api/internal/products/{$product->id}/availability?quantity=1"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'availableQuantity' => 0,
                    'sufficient' => false,
                ],
            ]);
    }

    // =========================================================
    // 顧客検索 API
    // =========================================================

    /**
     * 【テスト目的】 TC-U03: 顧客検索APIが部分一致でJSON配列を返す
     * 【テスト内容】 会社名・担当者名・電話番号の部分一致検索
     * 【期待される動作】 一致する顧客がJSON配列、該当なしは空配列
     * 🔵 REQ-011より
     */
    public function test_customer_search_returns_partial_match_results(): void
    {
        $user = $this->user();
        Customer::factory()->create(['company_name' => '株式会社テスト', 'contact_name' => '田中太郎', 'phone' => '03-1234-5678']);
        Customer::factory()->create(['company_name' => '山田商事', 'contact_name' => '山田花子', 'phone' => '06-9999-0000']);

        // 会社名部分一致
        $response = $this->actingAs($user)->get('/api/internal/customers/search?q=テスト');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('株式会社テスト', $data[0]['company_name']);

        // 該当なし → 空配列
        $response2 = $this->actingAs($user)->get('/api/internal/customers/search?q=XXXXXXXX');
        $response2->assertStatus(200);
        $this->assertEmpty($response2->json('data'));
    }

    // =========================================================
    // 製品検索 API
    // =========================================================

    /**
     * 【テスト目的】 TC-U04: 製品検索APIが部分一致でJSON配列を返す
     * 【テスト内容】 品番・製品名の部分一致検索
     * 【期待される動作】 一致する製品がJSON配列で返る
     * 🔵 REQ-021より
     */
    public function test_product_search_returns_partial_match_results(): void
    {
        $user = $this->user();
        Product::factory()->create(['product_code' => 'P-001', 'product_name' => 'テスト部品A']);
        Product::factory()->create(['product_code' => 'P-002', 'product_name' => '別製品B']);

        // 製品名部分一致
        $response = $this->actingAs($user)->get('/api/internal/products/search?q=テスト');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('テスト部品A', $data[0]['product_name']);
        $this->assertArrayHasKey('available_quantity', $data[0]);

        // キーワードなし → 空配列
        $response2 = $this->actingAs($user)->get('/api/internal/products/search?q=');
        $response2->assertStatus(200);
        $this->assertEmpty($response2->json('data'));
    }

    // =========================================================
    // 見積金額計算 API
    // =========================================================

    /**
     * 【テスト目的】 TC-U05: 見積金額計算APIが正しい小計・合計を返す
     * 【テスト内容】 複数明細行を送信して小計・合計を計算する
     * 【期待される動作】 quantity*unit_priceで各行の小計と全体合計が算出される
     * 🟡 api-endpoints.md「POST /api/internal/quotations/calculate」より
     */
    public function test_quotation_calculate_returns_correct_amounts(): void
    {
        $user = $this->user(UserRole::SALES);

        $response = $this->actingAs($user)->post('/api/internal/quotations/calculate', [
            'items' => [
                ['quantity' => 3, 'unit_price' => 10000],
                ['quantity' => 2, 'unit_price' => 5000],
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(30000, $data['items'][0]['amount']); // 3*10000
        $this->assertEquals(10000, $data['items'][1]['amount']); // 2*5000
        $this->assertEquals(40000, $data['total']);
    }

    // =========================================================
    // 認証・認可チェック
    // =========================================================

    /**
     * 【テスト目的】 TC-U06: 未認証・権限外アクセス時に適切なレスポンスを返す
     * 【テスト内容】 未認証でAPI呼び出し、warehouseロールで在庫チェック呼び出し
     * 【期待される動作】 未認証は401/302リダイレクト、warehouse権限なしは403
     * 🔵 api-endpoints.md「認証・権限制御」より
     */
    public function test_unauthenticated_access_is_rejected_and_warehouse_cannot_access(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);

        // 未認証 → 401またはリダイレクト
        $response = $this->get("/api/internal/products/{$product->id}/availability?quantity=1");
        $this->assertContains($response->status(), [302, 401]);

        // warehouseロールはsales系内部APIにアクセス不可
        $warehouse = $this->user(UserRole::WAREHOUSE);
        $response2 = $this->actingAs($warehouse)->get(
            "/api/internal/products/{$product->id}/availability?quantity=1"
        );
        $response2->assertStatus(403);
    }
}
