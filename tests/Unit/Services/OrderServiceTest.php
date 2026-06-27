<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0009 単体テスト: OrderService（TC-01〜TC-08）
 *
 * 現時点では OrderService が未実装のため、本テストはクラス未検出（Fatal Error）または
 * 機能未実装によりすべて失敗する（Redフェーズ）。
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0009/order-management-testcases.md TC-01〜TC-08
 */
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OrderService
    {
        return $this->app->make(OrderService::class);
    }

    /**
     * テストデータ準備ヘルパー: confirmed状態の受注と明細を生成する
     *
     * @param  array<int, array{quantity: int, stock: int, reserved: int}>  $items
     * @return array{order: SalesOrder, products: Product[]}
     */
    private function prepareOrder(OrderStatus $status, array $items): array
    {
        $customer = Customer::factory()->create();
        $creator = User::factory()->create(['role' => UserRole::SALES]);
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'created_by' => $creator->id,
        ]);

        $products = [];
        foreach ($items as $i => $itemDef) {
            $product = Product::factory()->create([
                'stock_quantity' => $itemDef['stock'],
                'reserved_quantity' => $itemDef['reserved'],
            ]);
            SalesOrderItem::factory()->create([
                'sales_order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $itemDef['quantity'],
                'unit_price' => 1000,
            ]);
            $products[] = $product;
        }

        return ['order' => $order, 'products' => $products];
    }

    /**
     * 【テスト目的】: cancel() が reserved_quantity を明細数量分減算し、stock_movements を記録し、ステータスを cancelled に変更することを確認する
     * 【テスト内容】: confirmed 受注に対して cancel() を実行し、在庫引当解除・変動履歴・ステータス更新が単一トランザクションでアトミックに実行されることを検証する
     * 【期待される動作】: reserved_quantity が減算され、reason=RESERVATION_RELEASE の stock_movements が記録され、status=CANCELLED・cancelled_at が記録される
     * 🔵 信頼性レベル: REQ-043・TASK-0009.md「テストケース1」・dataflow.md「受注ステータス遷移（受注確定→キャンセル 引当解除 REQ-043）」より直接抽出
     */
    public function test_cancel_releases_reserved_quantity_and_records_stock_movement(): void
    {
        // 【テストデータ準備】: 製品に reserved_quantity=10 を設定し、当該受注の明細 quantity=5 を用意する
        // 【初期条件設定】: キャンセル前の状態: reserved_quantity=10（うち5が当該受注分）
        $data = $this->prepareOrder(OrderStatus::CONFIRMED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 10],
        ]);
        $order = $data['order'];
        $product = $data['products'][0];

        // 【実際の処理実行】: OrderService::cancel() を呼び出す
        // 【処理内容】: DBトランザクション内でreserved_quantity減算・stock_movements記録・ステータス更新をアトミックに実行する想定
        $this->service()->cancel($order);

        // 【結果検証】: reserved_quantity が 10-5=5 に減算されることを確認する
        // 【期待値確認】: キャンセルにより在庫引当が解除され、利用可能在庫が回復することを保証するため
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 5,
        ]); // 【確認内容】: reserved_quantity が明細数量分(5)だけ減算されることを確認 🔵

        // 【結果検証】: stock_movements に reason=RESERVATION_RELEASE のレコードが記録されることを確認する
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovementReason::RESERVATION_RELEASE->value,
            'quantity_change' => -5,
        ]); // 【確認内容】: 引当解除の在庫変動履歴が記録されることを確認 🔵

        // 【結果検証】: status が CANCELLED(5) に更新され、cancelled_at が記録されることを確認する
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $fresh->status); // 【確認内容】: ステータスが CANCELLED に変更されることを確認 🔵
        $this->assertNotNull($fresh->cancelled_at); // 【確認内容】: cancelled_at にキャンセル日時が記録されることを確認 🔵
    }

    /**
     * 【テスト目的】: cancel() が shipping_instructed 状態の受注もキャンセルできることを確認する
     * 【テスト内容】: shipping_instructed(=2) の受注に対して cancel() を実行する
     * 【期待される動作】: status が CANCELLED(5) に更新され、在庫引当解除が実行される
     * 🔵 信頼性レベル: dataflow.md「受注ステータス遷移（出荷指示済み→キャンセル: 受注キャンセル REQ-043）」より直接抽出
     */
    public function test_cancel_works_for_shipping_instructed_status(): void
    {
        // 【テストデータ準備】: shipping_instructed 状態の受注を準備する
        $data = $this->prepareOrder(OrderStatus::SHIPPING_INSTRUCTED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 3],
        ]);
        $order = $data['order'];

        // 【実際の処理実行】: shipping_instructed 受注に対して cancel() を呼び出す
        $this->service()->cancel($order);

        // 【結果検証】: ステータスが CANCELLED になることを確認する
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status); // 【確認内容】: shipping_instructed からも CANCELLED に遷移できることを確認 🔵
    }

    /**
     * 【テスト目的】: cancel() が shipped 以降の受注はキャンセルできず例外をスローすることを確認する
     * 【テスト内容】: shipped(=3) の受注に対して cancel() を実行する
     * 【期待される動作】: 業務例外がスローされ、reserved_quantity・stock_movements・ステータスのいずれにも変更が加わらない
     * 🔵 信頼性レベル: TASK-0009.md「テストケース2」・dataflow.md「受注ステータス遷移（shipped以降→cancelledの遷移は定義されていない）」より直接抽出
     */
    public function test_cancel_throws_exception_when_order_is_already_shipped(): void
    {
        // 【テストデータ準備】: shipped(=3) 状態の受注を準備する
        // 【初期条件設定】: 出荷完了後はキャンセル不可というビジネスルールの確認
        $data = $this->prepareOrder(OrderStatus::SHIPPED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 0],
        ]);
        $order = $data['order'];
        $product = $data['products'][0];
        $initialReserved = $product->reserved_quantity;

        // 【実際の処理実行】: shipped 受注に対して cancel() を呼び出す
        // 【処理内容】: ガード処理が不正なステータス遷移を検出して例外をスローする想定
        $this->expectException(\InvalidArgumentException::class); // 【確認内容】: 業務例外がスローされることを確認 🔵

        $this->service()->cancel($order);

        // 【結果検証】: DB 変更が一切行われないことを確認する（例外後のロールバック保証）
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => $initialReserved,
        ]); // 【確認内容】: 例外スロー後も reserved_quantity が変更されていないことを確認 🔵
    }

    /**
     * 【テスト目的】: cancel() が invoiced 受注もキャンセルできないことを確認する
     * 【テスト内容】: invoiced(=4) の受注に対して cancel() を実行する
     * 【期待される動作】: 業務例外がスローされ、DB に変更が加わらない
     * 🟡 信頼性レベル: dataflow.md「受注ステータス遷移（invoiced以降→cancelledの遷移は定義されていない）」から妥当な推測
     */
    public function test_cancel_throws_exception_when_order_is_invoiced(): void
    {
        // 【テストデータ準備】: invoiced(=4) 状態の受注を準備する
        $data = $this->prepareOrder(OrderStatus::INVOICED, [
            ['quantity' => 2, 'stock' => 100, 'reserved' => 0],
        ]);
        $order = $data['order'];

        // 【実際の処理実行】: invoiced 受注に対して cancel() を呼び出す
        $this->expectException(\InvalidArgumentException::class); // 【確認内容】: 請求済み受注のキャンセルで業務例外がスローされることを確認 🟡

        $this->service()->cancel($order);
    }

    /**
     * 【テスト目的】: cancel() 後も reserved_quantity が 0 以上であることを保証する
     * 【テスト内容】: reserved_quantity が明細数量より少ない場合（不整合状態）に cancel() を実行する
     * 【期待される動作】: 例外がスローされ、ロールバック後も reserved_quantity が変更されない
     * 🔵 信頼性レベル: TASK-0009.md完了条件「キャンセル処理後も reserved_quantity >= 0 の整合性が保たれること」より直接抽出
     */
    public function test_cancel_ensures_reserved_quantity_does_not_go_negative(): void
    {
        // 【テストデータ準備】: reserved_quantity=2 に対して quantity=5 の明細を設定（不整合状態）
        // 【初期条件設定】: 引当数量より明細数量が多い = 負値になる可能性がある状態
        $data = $this->prepareOrder(OrderStatus::CONFIRMED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 2],
        ]);
        $order = $data['order'];
        $product = $data['products'][0];

        // 【実際の処理実行】: 不整合状態の受注に対して cancel() を呼び出す
        // 【処理内容】: アプリレベルの整合性チェックが負値を検出して例外をスローする想定
        $this->expectException(\RuntimeException::class); // 【確認内容】: reserved_quantity が負値になる場合は例外がスローされることを確認 🔵

        $this->service()->cancel($order);

        // 【結果検証】: reserved_quantity が変更されていないことを確認する
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 2,
        ]); // 【確認内容】: ロールバックにより reserved_quantity が元のまま保持されることを確認 🔵
    }

    /**
     * 【テスト目的】: cancel() が複数明細の受注で全製品の reserved_quantity を正しく減算することを確認する
     * 【テスト内容】: 明細が2行ある受注をキャンセルし、各製品の reserved_quantity が独立して減算されることを検証する
     * 【期待される動作】: 製品A・Bそれぞれの reserved_quantity が明細数量分だけ個別に減算され、stock_movements が2件記録される
     * 🔵 信頼性レベル: REQ-043・TASK-0009.md実装詳細4「各製品を lockForUpdate() で取得し reserved_quantity を減算する」より直接抽出
     */
    public function test_cancel_reduces_reserved_quantity_for_all_items(): void
    {
        // 【テストデータ準備】: 2製品の明細を持つ受注を準備する
        // 【初期条件設定】: 製品A(reserved=10, qty=3), 製品B(reserved=20, qty=7)
        $data = $this->prepareOrder(OrderStatus::CONFIRMED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 10],
            ['quantity' => 7, 'stock' => 80, 'reserved' => 20],
        ]);
        $order = $data['order'];
        $productA = $data['products'][0];
        $productB = $data['products'][1];

        // 【実際の処理実行】: 複数明細の受注をキャンセルする
        $this->service()->cancel($order);

        // 【結果検証】: 各製品の reserved_quantity が個別に減算されることを確認する
        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'reserved_quantity' => 7, // 10-3=7
        ]); // 【確認内容】: 製品A の reserved_quantity が 10-3=7 になることを確認 🔵
        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'reserved_quantity' => 13, // 20-7=13
        ]); // 【確認内容】: 製品B の reserved_quantity が 20-7=13 になることを確認 🔵

        // 【結果検証】: stock_movements に2件記録されることを確認する
        $this->assertDatabaseCount('stock_movements', 2); // 【確認内容】: 明細行数分の stock_movements が記録されることを確認 🔵
    }

    /**
     * 【テスト目的】: issueShippingInstruction() が confirmed → shipping_instructed へ正しく遷移することを確認する
     * 【テスト内容】: confirmed(=1) の受注に対して issueShippingInstruction() を実行する
     * 【期待される動作】: status が SHIPPING_INSTRUCTED(2) に更新される
     * 🔵 信頼性レベル: REQ-041・TASK-0009.md「テストケース4」・database-schema.sqlのステータスコード表より直接抽出
     */
    public function test_issue_shipping_instruction_changes_status_to_shipping_instructed(): void
    {
        // 【テストデータ準備】: confirmed(=1) 状態の受注を準備する
        $data = $this->prepareOrder(OrderStatus::CONFIRMED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 5],
        ]);
        $order = $data['order'];
        $product = $data['products'][0];
        $reservedBefore = $product->reserved_quantity;

        // 【実際の処理実行】: OrderService::issueShippingInstruction() を呼び出す
        // 【処理内容】: confirmed 受注のステータスを shipping_instructed へ変更する想定
        $this->service()->issueShippingInstruction($order);

        // 【結果検証】: ステータスが SHIPPING_INSTRUCTED に変更されることを確認する
        $this->assertSame(OrderStatus::SHIPPING_INSTRUCTED, $order->fresh()->status); // 【確認内容】: ステータスが shipping_instructed(2) になることを確認 🔵

        // 【結果検証】: reserved_quantity 等の在庫フィールドは変更されないことを確認する
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => $reservedBefore,
        ]); // 【確認内容】: 出荷指示発行では在庫数は変更されないことを確認 🔵
    }

    /**
     * 【テスト目的】: issueShippingInstruction() が既に shipping_instructed の受注では例外をスローすることを確認する
     * 【テスト内容】: shipping_instructed(=2) の受注に対して issueShippingInstruction() を実行する
     * 【期待される動作】: 業務例外がスローされ、ステータスが変更されない
     * 🔵 信頼性レベル: TASK-0009.md実装詳細5「すでに出荷指示済み...からの発行操作はエラーとして扱い、適切なメッセージを返す」より直接抽出
     */
    public function test_issue_shipping_instruction_throws_exception_when_already_shipping_instructed(): void
    {
        // 【テストデータ準備】: すでに shipping_instructed(=2) 状態の受注を準備する
        $data = $this->prepareOrder(OrderStatus::SHIPPING_INSTRUCTED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 3],
        ]);
        $order = $data['order'];

        // 【実際の処理実行】: 既に shipping_instructed の受注に対して issueShippingInstruction() を呼び出す
        $this->expectException(\InvalidArgumentException::class); // 【確認内容】: 不正なステータス遷移で業務例外がスローされることを確認 🔵

        $this->service()->issueShippingInstruction($order);
    }

    /**
     * 【テスト目的】: issueShippingInstruction() が cancelled 受注では例外をスローすることを確認する
     * 【テスト内容】: cancelled(=5) の受注に対して issueShippingInstruction() を実行する
     * 【期待される動作】: 業務例外がスローされ、ステータスが変更されない
     * 🔵 信頼性レベル: dataflow.md「受注ステータス遷移（キャンセル済み→出荷指示の遷移は定義されていない）」より直接抽出
     */
    public function test_issue_shipping_instruction_throws_exception_for_cancelled_order(): void
    {
        // 【テストデータ準備】: cancelled(=5) 状態の受注を準備する
        $data = $this->prepareOrder(OrderStatus::CANCELLED, [
            ['quantity' => 2, 'stock' => 100, 'reserved' => 0],
        ]);
        $order = $data['order'];
        $order->update(['cancelled_at' => now()]);

        // 【実際の処理実行】: キャンセル済み受注に対して issueShippingInstruction() を呼び出す
        $this->expectException(\InvalidArgumentException::class); // 【確認内容】: キャンセル済みからの出荷指示発行で例外がスローされることを確認 🔵

        $this->service()->issueShippingInstruction($order);
    }
}
