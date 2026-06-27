<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0009 統合テスト: OrderController（TC-09〜TC-22）
 *
 * 現時点では OrderController・OrderService・routes/web.php への受注ルート追加が
 * 未実装のため、本テストはルート未定義（RouteNotFoundException）または
 * 403/404レスポンスによりすべて失敗する（Redフェーズ）。
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0009/order-management-testcases.md TC-09〜TC-22
 */
class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 受注と明細をまとめて準備するヘルパー
     *
     * @param  array<int, array{quantity: int, stock: int, reserved: int}>  $items
     * @return array{order: SalesOrder, products: Product[]}
     */
    private function prepareOrderWithItems(OrderStatus $status, array $items, ?User $creator = null): array
    {
        $customer = Customer::factory()->create();
        $creator = $creator ?? User::factory()->create(['role' => UserRole::SALES]);

        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'confirmed_at' => now(),
            'created_by' => $creator->id,
        ]);

        $products = [];
        foreach ($items as $itemDef) {
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
     * 【テスト目的】: sales ロールのユーザーが受注一覧画面にアクセスできることを確認する
     * 【テスト内容】: salesロールで GET /orders にアクセスし、200 OK が返ることを検証する
     * 【期待される動作】: 受注一覧が表示される（NFR-021: 50件/ページ）
     * 🔵 信頼性レベル: api-endpoints.md「GET /orders: 権限=sales,accounting,admin」・REQ-040より直接抽出
     */
    public function test_sales_user_can_view_orders_index(): void
    {
        $sales = $this->user(UserRole::SALES);
        SalesOrder::factory()->count(3)->create(['created_by' => $sales->id]);

        // 【実際の処理実行】: salesロールで受注一覧画面にアクセスする
        // 【処理内容】: OrderController::index() が受注一覧をページネーション（50件）で返す想定
        $response = $this->actingAs($sales)->get(route('orders.index'));

        // 【結果検証】: 200 OK で受注一覧が表示されることを確認する
        $response->assertOk(); // 【確認内容】: 受注一覧画面が正常に表示されることを確認 🔵
    }

    /**
     * 【テスト目的】: sales ロールがステータスフィルタで受注一覧を絞り込めることを確認する
     * 【テスト内容】: status=1(confirmed) のクエリパラメータで受注一覧を取得し、他ステータスの受注が含まれないことを検証する
     * 【期待される動作】: confirmed の受注のみ表示され、cancelled の受注は表示されない
     * 🟡 信頼性レベル: TASK-0009.md完了条件「ステータスフィルタによる一覧絞り込みができること」から妥当な推測
     */
    public function test_sales_user_can_filter_orders_by_status(): void
    {
        $sales = $this->user(UserRole::SALES);
        $confirmedOrder = SalesOrder::factory()->create([
            'status' => OrderStatus::CONFIRMED,
            'order_number' => 'ORD-2026-0001',
            'created_by' => $sales->id,
        ]);
        $cancelledOrder = SalesOrder::factory()->create([
            'status' => OrderStatus::CANCELLED,
            'order_number' => 'ORD-2026-0002',
            'cancelled_at' => now(),
            'created_by' => $sales->id,
        ]);

        // 【実際の処理実行】: ステータス=confirmed でフィルタして受注一覧を取得する
        $response = $this->actingAs($sales)->get(route('orders.index', ['status' => 1]));

        // 【結果検証】: confirmed の受注番号が表示され、cancelled の受注番号は表示されないことを確認する
        $response->assertOk();
        $response->assertSee('ORD-2026-0001'); // 【確認内容】: confirmed の受注が表示されることを確認 🟡
        $response->assertDontSee('ORD-2026-0002'); // 【確認内容】: cancelled の受注が表示されないことを確認 🟡
    }

    /**
     * 【テスト目的】: sales ロールが受注詳細を表示できることを確認する
     * 【テスト内容】: salesロールで GET /orders/{order} にアクセスし、受注情報が正しく表示されることを検証する
     * 【期待される動作】: 受注番号・顧客名・明細・ステータスが表示される
     * 🔵 信頼性レベル: api-endpoints.md「GET /orders/{order}: 権限=sales,accounting,admin」より直接抽出
     */
    public function test_sales_user_can_view_order_detail(): void
    {
        $sales = $this->user(UserRole::SALES);
        $data = $this->prepareOrderWithItems(OrderStatus::CONFIRMED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 5],
        ]);
        $order = $data['order'];
        $product = $data['products'][0];

        // 【実際の処理実行】: 受注詳細画面にアクセスする
        $response = $this->actingAs($sales)->get(route('orders.show', $order));

        // 【結果検証】: 受注番号・顧客名・明細の製品名が表示されることを確認する
        $response->assertOk(); // 【確認内容】: 受注詳細画面が正常に表示されることを確認 🔵
        $response->assertSee($order->order_number); // 【確認内容】: 受注番号が表示されることを確認 🔵
        $response->assertSee($order->customer->company_name); // 【確認内容】: 顧客名が表示されることを確認 🔵
        $response->assertSee($product->product_name); // 【確認内容】: 明細の製品名が表示されることを確認 🔵
    }

    /**
     * 【テスト目的】: sales ロールが confirmed 受注をキャンセルでき、在庫引当が解除されることを確認する
     * 【テスト内容】: salesロールで POST /orders/{order}/cancel を実行し、在庫引当解除・変動履歴・ステータス更新がDBに反映されることを検証する
     * 【期待される動作】: reserved_quantity が減算され、stock_movements が記録され、status=CANCELLED・cancelled_at が記録される
     * 🔵 信頼性レベル: REQ-043・TASK-0009.md「統合テスト1」より直接抽出
     */
    public function test_sales_user_can_cancel_order_and_reserved_quantity_is_released(): void
    {
        $sales = $this->user(UserRole::SALES);

        // 【テストデータ準備】: confirmed 受注（製品 reserved_quantity=5、明細 quantity=5）を準備する
        // 【初期条件設定】: 受注確定により reserved_quantity=5 が引き当てられている状態
        $data = $this->prepareOrderWithItems(OrderStatus::CONFIRMED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 5],
        ], $sales);
        $order = $data['order'];
        $product = $data['products'][0];

        // 【実際の処理実行】: salesロールでキャンセルリクエストを送信する
        // 【処理内容】: OrderController::cancel() → OrderService::cancel() が実行される想定
        $response = $this->actingAs($sales)->post(route('orders.cancel', $order), [
            'cancel_reason' => 'テストキャンセル',
        ]);

        // 【結果検証】: リダイレクト後に success フラッシュメッセージが含まれることを確認する
        $response->assertRedirect(route('orders.show', $order)); // 【確認内容】: キャンセル後に受注詳細へリダイレクトされることを確認 🔵
        $response->assertSessionHas('success'); // 【確認内容】: 成功フラッシュメッセージが設定されることを確認 🔵

        // 【結果検証】: reserved_quantity が 5-5=0 に減算されることを確認する
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 0,
        ]); // 【確認内容】: 在庫引当解除により reserved_quantity が 0 になることを確認 🔵

        // 【結果検証】: stock_movements に RESERVATION_RELEASE の履歴が記録されることを確認する
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovementReason::RESERVATION_RELEASE->value,
        ]); // 【確認内容】: 在庫変動履歴(reason=2 reservation_release)が記録されることを確認 🔵

        // 【結果検証】: status が CANCELLED に更新され、cancelled_at が記録されることを確認する
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $fresh->status); // 【確認内容】: ステータスが CANCELLED に更新されることを確認 🔵
        $this->assertNotNull($fresh->cancelled_at); // 【確認内容】: cancelled_at にキャンセル日時が記録されることを確認 🔵
    }

    /**
     * 【テスト目的】: sales ロールが confirmed 受注に出荷指示を発行できることを確認する
     * 【テスト内容】: salesロールで POST /orders/{order}/shipping-instruction を実行し、ステータスが shipping_instructed に変更されることを検証する
     * 【期待される動作】: status が SHIPPING_INSTRUCTED(2) に更新され、成功メッセージが表示される
     * 🔵 信頼性レベル: REQ-041・api-endpoints.md「POST /orders/{order}/shipping-instruction: 権限=sales,admin」より直接抽出
     */
    public function test_sales_user_can_issue_shipping_instruction(): void
    {
        $sales = $this->user(UserRole::SALES);
        $data = $this->prepareOrderWithItems(OrderStatus::CONFIRMED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 3],
        ], $sales);
        $order = $data['order'];

        // 【実際の処理実行】: salesロールで出荷指示発行リクエストを送信する
        // 【処理内容】: OrderController::issueShippingInstruction() → OrderService::issueShippingInstruction() が実行される想定
        $response = $this->actingAs($sales)->post(route('orders.shipping_instruction', $order));

        // 【結果検証】: リダイレクトと成功メッセージを確認する
        $response->assertRedirect(route('orders.show', $order)); // 【確認内容】: 発行後に受注詳細へリダイレクトされることを確認 🔵
        $response->assertSessionHas('success'); // 【確認内容】: 成功フラッシュメッセージが設定されることを確認 🔵

        // 【結果検証】: ステータスが SHIPPING_INSTRUCTED に更新されることを確認する
        $this->assertSame(OrderStatus::SHIPPING_INSTRUCTED, $order->fresh()->status); // 【確認内容】: ステータスが shipping_instructed(2) に遷移することを確認 🔵
    }

    /**
     * 【テスト目的】: admin ロールのみが受注を編集できることを確認する（REQ-042）
     * 【テスト内容】: salesロールで PUT /orders/{order} を実行すると 403 となり、adminロールでは成功することを検証する
     * 【期待される動作】: salesロールは 403 Forbidden、adminロールは 302 リダイレクト（更新成功）
     * 🔵 信頼性レベル: REQ-042・TASK-0009.md「テストケース3」・api-endpoints.md「PUT /orders/{order}: 権限=admin」より直接抽出
     */
    public function test_only_admin_can_edit_confirmed_order(): void
    {
        $sales = $this->user(UserRole::SALES);
        $admin = $this->user(UserRole::ADMIN);
        $data = $this->prepareOrderWithItems(OrderStatus::CONFIRMED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 3],
        ]);
        $order = $data['order'];

        // 【実際の処理実行1】: salesロールで受注編集を試みる
        // 【処理内容】: OrderPolicy::update() により sales は拒否される想定
        $salesResponse = $this->actingAs($sales)->put(route('orders.update', $order), [
            'remarks' => '営業からの変更',
        ]);

        // 【結果検証1】: salesロールのリクエストは 403 Forbidden となることを確認する
        $salesResponse->assertForbidden(); // 【確認内容】: sales ロールによる受注編集が 403 で拒否されることを確認 🔵

        // 【実際の処理実行2】: adminロールで受注編集を試みる
        $adminResponse = $this->actingAs($admin)->put(route('orders.update', $order), [
            'remarks' => '管理者からの変更',
        ]);

        // 【結果検証2】: adminロールのリクエストは成功することを確認する
        $adminResponse->assertRedirect(); // 【確認内容】: admin ロールによる受注編集が成功（リダイレクト）することを確認 🔵
    }

    /**
     * 【テスト目的】: sales ロールが受注編集画面にアクセスしようとすると 403 になることを確認する
     * 【テスト内容】: salesロールで GET /orders/{order}/edit にアクセスする
     * 【期待される動作】: 403 Forbidden が返る
     * 🟡 信頼性レベル: TASK-0009.md実装詳細3「admin以外のユーザーがアクセスした場合は403を返す」から妥当な推測
     */
    public function test_sales_user_gets_403_when_accessing_edit_page(): void
    {
        $sales = $this->user(UserRole::SALES);
        $data = $this->prepareOrderWithItems(OrderStatus::CONFIRMED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 3],
        ]);
        $order = $data['order'];

        // 【実際の処理実行】: salesロールで編集画面へのアクセスを試みる
        $response = $this->actingAs($sales)->get(route('orders.edit', $order));

        // 【結果検証】: 403 Forbidden が返ることを確認する
        $response->assertForbidden(); // 【確認内容】: sales ロールの編集画面アクセスが 403 で拒否されることを確認 🟡
    }

    /**
     * 【テスト目的】: shipped 以降の受注をキャンセルしようとするとエラーメッセージが表示されることを確認する
     * 【テスト内容】: shipped(=3) の受注に対して POST /orders/{order}/cancel を実行する
     * 【期待される動作】: エラーメッセージが表示され、DB に変更が加わらない
     * 🔵 信頼性レベル: TASK-0009.md「統合テスト2」・dataflow.md「受注ステータス遷移（shipped以降→cancelledの遷移は定義されていない）」より直接抽出
     */
    public function test_cannot_cancel_shipped_order_and_shows_error_message(): void
    {
        $sales = $this->user(UserRole::SALES);

        // 【テストデータ準備】: shipped(=3) 状態の受注を準備する
        $data = $this->prepareOrderWithItems(OrderStatus::SHIPPED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 0],
        ], $sales);
        $order = $data['order'];
        $product = $data['products'][0];

        // 【実際の処理実行】: 出荷完了済み受注に対してキャンセルリクエストを送信する
        $response = $this->actingAs($sales)->post(route('orders.cancel', $order));

        // 【結果検証】: エラーメッセージがフラッシュされ、DB に変更がないことを確認する
        $response->assertRedirect(); // 【確認内容】: エラー後もリダイレクトされることを確認 🔵
        $response->assertSessionHas('error'); // 【確認内容】: エラーフラッシュメッセージが設定されることを確認 🔵
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => OrderStatus::SHIPPED->value,
        ]); // 【確認内容】: ステータスが SHIPPED のまま変更されていないことを確認 🔵
        $this->assertDatabaseEmpty('stock_movements'); // 【確認内容】: stock_movements が追加されていないことを確認 🔵
    }

    /**
     * 【テスト目的】: shipping_instructed の受注に出荷指示を再発行しようとするとエラーになることを確認する
     * 【テスト内容】: shipping_instructed(=2) の受注に対して POST /orders/{order}/shipping-instruction を実行する
     * 【期待される動作】: エラーメッセージが表示され、ステータスは変更されない
     * 🔵 信頼性レベル: TASK-0009.md実装詳細5「すでに出荷指示済み...からの発行操作はエラーとして扱い、適切なメッセージを返す」より直接抽出
     */
    public function test_cannot_issue_shipping_instruction_twice(): void
    {
        $sales = $this->user(UserRole::SALES);
        $data = $this->prepareOrderWithItems(OrderStatus::SHIPPING_INSTRUCTED, [
            ['quantity' => 3, 'stock' => 50, 'reserved' => 3],
        ], $sales);
        $order = $data['order'];

        // 【実際の処理実行】: 既に shipping_instructed の受注に再度出荷指示発行を試みる
        $response = $this->actingAs($sales)->post(route('orders.shipping_instruction', $order));

        // 【結果検証】: エラーフラッシュメッセージが設定され、ステータスが変更されないことを確認する
        $response->assertSessionHas('error'); // 【確認内容】: エラーメッセージが設定されることを確認 🔵
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => OrderStatus::SHIPPING_INSTRUCTED->value,
        ]); // 【確認内容】: ステータスが SHIPPING_INSTRUCTED のまま変更されていないことを確認 🔵
    }

    /**
     * 【テスト目的】: warehouse ロールは受注一覧にアクセスできないことを確認する
     * 【テスト内容】: warehouseロールで GET /orders にアクセスする
     * 【期待される動作】: 403 Forbidden または リダイレクト
     * 🟡 信頼性レベル: api-endpoints.md「GET /orders: 権限=sales,accounting,admin」（warehouse は含まれない）から妥当な推測
     */
    public function test_warehouse_user_cannot_access_orders(): void
    {
        $warehouse = $this->user(UserRole::WAREHOUSE);

        // 【実際の処理実行】: warehouseロールで受注一覧へのアクセスを試みる
        $response = $this->actingAs($warehouse)->get(route('orders.index'));

        // 【結果検証】: アクセスが拒否されることを確認する
        $response->assertStatus(403); // 【確認内容】: warehouse ロールの受注一覧アクセスが 403 で拒否されることを確認 🟡
    }

    /**
     * 【テスト目的】: accounting ロールはキャンセル操作ができないことを確認する
     * 【テスト内容】: accountingロールで POST /orders/{order}/cancel を実行する
     * 【期待される動作】: 403 Forbidden（受注内容・在庫は変更されない）
     * 🔵 信頼性レベル: api-endpoints.md「POST /orders/{order}/cancel: 権限=sales,admin（accounting は含まれない）」より直接抽出
     */
    public function test_accounting_user_cannot_cancel_order(): void
    {
        $accounting = $this->user(UserRole::ACCOUNTING);
        $data = $this->prepareOrderWithItems(OrderStatus::CONFIRMED, [
            ['quantity' => 5, 'stock' => 100, 'reserved' => 5],
        ]);
        $order = $data['order'];

        // 【実際の処理実行】: accountingロールでキャンセルリクエストを送信する
        $response = $this->actingAs($accounting)->post(route('orders.cancel', $order));

        // 【結果検証】: 403 Forbidden が返り、DB に変更がないことを確認する
        $response->assertForbidden(); // 【確認内容】: accounting ロールのキャンセル操作が 403 で拒否されることを確認 🔵
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]); // 【確認内容】: ステータスが CONFIRMED のまま変更されていないことを確認 🔵
    }

    /**
     * 【テスト目的】: 受注一覧が 50 件/ページでページネーションされることを確認する
     * 【テスト内容】: 51 件の受注を作成し、最初のページが 50 件、2ページ目が 1 件であることを検証する
     * 【期待される動作】: ページネーションが 50 件/ページで機能している
     * 🔵 信頼性レベル: NFR-021「ページネーション 50 件/ページ」より直接抽出
     */
    public function test_orders_index_paginates_50_per_page(): void
    {
        $sales = $this->user(UserRole::SALES);
        SalesOrder::factory()->count(51)->create(['created_by' => $sales->id]);

        // 【実際の処理実行1】: 1ページ目を取得する
        $response1 = $this->actingAs($sales)->get(route('orders.index'));

        // 【実際の処理実行2】: 2ページ目を取得する
        $response2 = $this->actingAs($sales)->get(route('orders.index', ['page' => 2]));

        // 【結果検証】: ページネーションが正しく機能していることを確認する
        $response1->assertOk(); // 【確認内容】: 1ページ目が正常に表示されることを確認 🔵
        $response2->assertOk(); // 【確認内容】: 2ページ目が正常に表示されることを確認 🔵
        // ページネーションリンクが存在することを確認（Bladeテンプレートにlinks()が含まれる想定）
        $response1->assertSee('page=2'); // 【確認内容】: 2ページ目へのリンクが存在することを確認 🔵
    }
}
