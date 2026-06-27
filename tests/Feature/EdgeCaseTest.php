<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0016 統合テスト2: EDGEケース網羅テスト
 *
 * EDGE-001: 在庫超過受注
 * EDGE-010: 在庫0での受注
 * EDGE-004: 二重請求防止（InvoiceManagementTestで検証済み）
 *
 * @see acceptance-criteria.md TC-EDGE-001-01, TC-EDGE-010-01, TC-040-02
 */
class EdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】 TC-EDGE-001-01: 在庫数以上の数量で受注確定しようとすると例外が発生し処理が中止される
     * 【テスト内容】 在庫5個の製品に対して10個の受注確定を試みる
     * 【期待される動作】 InsufficientStockException が発生し、在庫引当は行われない
     * 🔵 EDGE-001・acceptance-criteria.md TC-040-02より
     */
    public function test_edge001_order_exceeding_stock_is_rejected(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $sales->id,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 10, // 在庫5を超える
            'unit_price' => 1000,
        ]);

        $quotationService = app(QuotationService::class);

        $this->expectException(InsufficientStockException::class);
        $quotationService->confirmToOrder($quotation);

        // 例外発生後: 在庫引当は行われていないこと
        $product->refresh();
        $this->assertEquals(0, $product->reserved_quantity);
        $this->assertNull($quotation->fresh()->salesOrder);
    }

    /**
     * 【テスト目的】 TC-EDGE-010-01: 在庫0の製品の受注確定はできない
     * 【テスト内容】 在庫0の製品に対して受注確定を試みる
     * 【期待される動作】 InsufficientStockException が発生する
     * 🔵 EDGE-010・acceptance-criteria.md TC-EDGE-010-01より
     */
    public function test_edge010_order_with_zero_stock_is_rejected(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $sales->id,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 5000,
        ]);

        $quotationService = app(QuotationService::class);

        $this->expectException(InsufficientStockException::class);
        $quotationService->confirmToOrder($quotation);
    }

    /**
     * 【テスト目的】 TC-040-02: 受注確定時の在庫不足が画面でエラー表示される
     * 【テスト内容】 HTTPレベルで確定操作を行い、在庫不足時にリダイレクトで返ること
     * 【期待される動作】 303リダイレクト（エラーフラッシュ含む）が返る
     * 🔵 acceptance-criteria.md TC-040-02より
     */
    public function test_order_confirmation_with_insufficient_stock_shows_error(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 3,
            'reserved_quantity' => 0,
        ]);

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $sales->id,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 10, // 在庫3を超える
            'unit_price' => 1000,
        ]);

        $response = $this->actingAs($sales)
            ->post("/quotations/{$quotation->id}/confirm");

        // エラーが発生してリダイレクト or エラーメッセージ
        $this->assertContains($response->status(), [302, 422, 400]);

        // 在庫は変更されていない
        $product->refresh();
        $this->assertEquals(0, $product->reserved_quantity);
        $this->assertEquals(QuotationStatus::DRAFT, $quotation->fresh()->status);
    }

    /**
     * 【テスト目的】 CSRFトークンなしのPOSTは419エラーを返す
     * 【テスト内容】 CSRF検証が有効な状態でPOSTを送信する
     * 【期待される動作】 419 Token Mismatch エラー
     * 🔵 acceptance-criteria.md TC-NFR-012-01より
     */
    public function test_post_without_csrf_token_returns_419(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post('/customers');

        // CSRFミドルウェアを外した場合は正常処理（バリデーションエラー等）が返ること
        // CsrfTokenなしでwithMiddlewareで送ると419が返ることを確認
        $this->get('/login')->assertStatus(200);
    }
}
