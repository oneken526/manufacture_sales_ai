<?php

namespace Tests\Feature\Customers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0005 統合テスト1・2に対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0005.md 統合テスト要件
 */
class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】: 顧客登録→検索→詳細表示→編集→削除の一連フローがエラーなく完了することを確認する
     * 【テスト内容】: sales権限で登録・検索・詳細・編集を行い、admin権限で削除を行う
     * 【期待される動作】: 各画面で正しいデータが表示され、最終的にソフトデリートされる
     * 🔵 信頼性レベル: TASK-0005.md統合テスト1（REQ-010〜REQ-013）に直接基づく
     */
    public function test_customer_can_be_registered_searched_viewed_updated_and_deleted(): void
    {
        $sales = $this->user(UserRole::SALES);

        // 【実際の処理実行】: sales権限のユーザーで顧客登録フォームから新規顧客を登録する
        $storeResponse = $this->actingAs($sales)->post(route('customers.store'), [
            'company_name' => '株式会社統合テスト商事',
            'contact_name' => '統合 花子',
            'address' => '東京都港区1-2-3',
            'phone' => '03-9999-0000',
            'email' => 'integration@example.com',
            'credit_limit' => 1_500_000,
        ]);

        $customer = Customer::where('company_name', '株式会社統合テスト商事')->firstOrFail();
        $storeResponse->assertRedirect(route('customers.show', $customer)); // 【確認内容】: 登録後に詳細画面へリダイレクトされることを確認 🔵
        $this->assertDatabaseHas('customers', ['company_name' => '株式会社統合テスト商事']); // 【確認内容】: 入力内容がDBに登録されることを確認 🔵

        // 【実際の処理実行】: 一覧画面の検索フォームに会社名の一部を入力し検索結果に表示されることを確認する
        $searchResponse = $this->actingAs($sales)->get(route('customers.index', ['q' => '統合テスト']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('株式会社統合テスト商事'); // 【確認内容】: 検索結果に対象顧客が表示されることを確認 🔵

        // 【実際の処理実行】: 検索結果から顧客詳細画面に遷移する
        $showResponse = $this->actingAs($sales)->get(route('customers.show', $customer));
        $showResponse->assertOk();
        $showResponse->assertSee('株式会社統合テスト商事'); // 【確認内容】: 登録内容が詳細画面に表示されることを確認 🔵
        $showResponse->assertSee('受注履歴はありません'); // 【確認内容】: 受注履歴が空であることが表示されることを確認 🔵

        // 【実際の処理実行】: 編集フォームから顧客情報を更新する
        $updateResponse = $this->actingAs($sales)->put(route('customers.update', $customer), [
            'company_name' => '株式会社統合テスト商事（更新後）',
            'contact_name' => '統合 花子',
            'address' => '東京都港区1-2-3',
            'phone' => '03-9999-0000',
            'email' => 'integration@example.com',
            'credit_limit' => 2_000_000,
        ]);
        $updateResponse->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'company_name' => '株式会社統合テスト商事（更新後）',
            'credit_limit' => 2_000_000,
        ]); // 【確認内容】: 編集内容が反映されることを確認 🔵

        // 【実際の処理実行】: admin権限のユーザーで削除操作を行う
        $admin = $this->user(UserRole::ADMIN);
        $destroyResponse = $this->actingAs($admin)->delete(route('customers.destroy', $customer));
        $destroyResponse->assertRedirect(route('customers.index'));

        // 【結果検証】: 顧客がソフトデリートされ、一覧から消えることを確認する
        $this->assertSoftDeleted('customers', ['id' => $customer->id]); // 【確認内容】: 削除がソフトデリートとして記録されることを確認 🔵
        $indexResponse = $this->actingAs($admin)->get(route('customers.index'));
        $indexResponse->assertDontSee('株式会社統合テスト商事（更新後）'); // 【確認内容】: 削除後に一覧から表示されなくなることを確認 🔵
    }

    /**
     * 【テスト目的】: 受注がある顧客の削除が拒否され、警告メッセージが表示されることを確認する
     * 【テスト内容】: 受注（sales_orders）が紐づく顧客に対してadmin権限で削除操作を行う
     * 【期待される動作】: 削除が実行されず、「この顧客には受注履歴があるため削除できません」という警告が表示される
     * 🟡 信頼性レベル: TASK-0005.md統合テスト2（REQ-012）に基づく
     */
    public function test_deleting_customer_with_orders_is_rejected_with_warning_message(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        // 【テストデータ準備】: 受注（sales_orders）が紐づく顧客を用意する
        $customer = Customer::factory()->create();
        SalesOrder::factory()->for($customer)->create();

        // 【実際の処理実行】: admin権限のユーザーで削除操作を行う
        $response = $this->actingAs($admin)->delete(route('customers.destroy', $customer));

        // 【結果検証】: 削除が拒否され、詳細画面へ警告メッセージ付きでリダイレクトされることを確認する
        $response->assertRedirect(route('customers.show', $customer));
        $response->assertSessionHas('error', 'この顧客には受注履歴があるため削除できません'); // 【確認内容】: 警告メッセージが画面に渡されることを確認 🟡

        // 【結果検証】: 顧客レコードが削除されずに残っていることを確認する
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]); // 【確認内容】: 削除が実行されず顧客が残っていることを確認 🟡

        $showResponse = $this->actingAs($admin)->get(route('customers.show', $customer));
        $showResponse->assertOk(); // 【確認内容】: 顧客詳細画面に引き続きアクセス可能であることを確認 🟡
    }
}
