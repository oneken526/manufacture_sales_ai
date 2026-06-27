<?php

namespace Tests\Unit\Services;

use App\DataTransferObjects\CustomerData;
use App\Exceptions\CustomerHasOrdersException;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0005 単体テストケース1・2に対応するテスト。
 *
 * @see .docs/tasks/manufacture-sales-system/TASK-0005.md 単体テスト要件
 */
class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CustomerService
    {
        return $this->app->make(CustomerService::class);
    }

    /**
     * 【テスト目的】: CustomerServiceのCRUD操作（create/find/update/delete）が正しく動作することを確認する
     * 【テスト内容】: 有効な顧客データでcreate→find→update→deleteを順に呼び出す
     * 【期待される動作】: 各操作の結果がデータベースおよび取得結果に正しく反映される
     * 🔵 信頼性レベル: TASK-0005.md単体テストケース1（REQ-010）に直接基づく
     */
    public function test_crud_operations_persist_and_retrieve_customer_correctly(): void
    {
        $service = $this->service();

        // 【テストデータ準備】: 会社名・担当者名・住所・電話・メール・与信枠を備えた有効な顧客データを用意する
        $createData = new CustomerData(
            id: null,
            companyName: '株式会社テスト商事',
            contactName: '田中 太郎',
            address: '東京都千代田区1-1-1',
            phone: '03-0000-0001',
            email: 'tanaka@example.com',
            creditLimit: 1_000_000,
        );

        // 【実際の処理実行】: create()で新規顧客を登録する
        $created = $service->create($createData);

        // 【結果検証】: 入力データがそのままDBへ反映されていることを確認する
        $this->assertDatabaseHas('customers', [
            'id' => $created->id,
            'company_name' => '株式会社テスト商事',
            'contact_name' => '田中 太郎',
            'credit_limit' => 1_000_000,
        ]); // 【確認内容】: create()の登録結果がデータベースに正しく反映されていることを確認 🔵

        // 【実際の処理実行】: find()で登録した顧客を取得する
        $found = $service->find($created->id);

        // 【結果検証】: 取得結果が登録した内容と一致することを確認する
        $this->assertNotNull($found); // 【確認内容】: find()が登録済みレコードを取得できることを確認 🔵
        $this->assertSame('株式会社テスト商事', $found->company_name); // 【確認内容】: 取得結果が入力データと一致することを確認 🔵

        // 【実際の処理実行】: update()で顧客情報を更新する
        $updateData = new CustomerData(
            id: $created->id,
            companyName: '株式会社テスト商事（改称）',
            contactName: '田中 太郎',
            address: '東京都千代田区1-1-1',
            phone: '03-0000-0001',
            email: 'tanaka@example.com',
            creditLimit: 2_000_000,
        );
        $updated = $service->update($created->id, $updateData);

        // 【結果検証】: 更新内容がDBへ反映されていることを確認する
        $this->assertSame('株式会社テスト商事（改称）', $updated->company_name); // 【確認内容】: update()の戻り値が更新後の内容になっていることを確認 🔵
        $this->assertDatabaseHas('customers', [
            'id' => $created->id,
            'company_name' => '株式会社テスト商事（改称）',
            'credit_limit' => 2_000_000,
        ]); // 【確認内容】: update()の更新結果がデータベースに正しく反映されていることを確認 🔵

        // 【実際の処理実行】: delete()で顧客を削除する（受注が存在しないため成功する）
        $service->delete($created->id);

        // 【結果検証】: ソフトデリートによりdeleted_atが記録されていることを確認する
        $this->assertSoftDeleted('customers', ['id' => $created->id]); // 【確認内容】: delete()がソフトデリートとして記録されることを確認 🔵
    }

    /**
     * 【テスト目的】: 受注が存在する顧客の削除が拒否されることを確認する
     * 【テスト内容】: sales_ordersに紐づく受注が存在する顧客に対してdelete()を呼び出す
     * 【期待される動作】: CustomerHasOrdersExceptionがスローされ、顧客は削除されずdeleted_atもNULLのままとなる
     * 🟡 信頼性レベル: TASK-0005.md単体テストケース2（REQ-012）に基づく
     */
    public function test_delete_throws_exception_when_customer_has_orders(): void
    {
        $service = $this->service();

        // 【テストデータ準備】: 受注（sales_orders）が紐づく顧客を用意する
        $customer = Customer::factory()->create();
        SalesOrder::factory()->for($customer)->create();

        // 【実際の処理実行】: 受注が存在する顧客に対して削除を試みる
        // 【結果検証】: CustomerHasOrdersExceptionがスローされることを確認する
        $this->expectException(CustomerHasOrdersException::class); // 【確認内容】: 受注が存在する場合に専用例外がスローされることを確認 🟡

        try {
            $service->delete($customer->id);
        } finally {
            // 【結果検証】: 例外発生後も顧客レコードが削除されておらず、deleted_atがNULLのままであることを確認する
            $this->assertDatabaseHas('customers', [
                'id' => $customer->id,
                'deleted_at' => null,
            ]); // 【確認内容】: 削除が実行されずdeleted_atがNULLのままであることを確認 🟡
        }
    }
}
