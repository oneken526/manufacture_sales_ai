<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * TASK-0002 単体テスト要件 テストケース5・統合テスト1 に対応する検証。
 *
 * @see docs/tasks/manufacture-sales-system/TASK-0002.md
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_initial_admin_user_with_hashed_password(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin, '初期管理者ユーザーが作成されていること');
        $this->assertSame(UserRole::ADMIN, $admin->role, '管理者ユーザーのroleはUserRole::ADMINであること');
        $this->assertSame(1, $admin->role->value);
        $this->assertTrue(Hash::check('password', $admin->getAttributes()['password']), 'パスワードはbcryptでハッシュ化されていること');
        $this->assertNotSame('password', $admin->getAttributes()['password']);
    }

    public function test_seeder_creates_users_for_each_role(): void
    {
        $this->seed();

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(
                User::where('role', $role->value)->exists(),
                "role={$role->value}（{$role->label()}）のユーザーが作成されていること"
            );
        }
    }

    public function test_seeder_creates_sample_customers_and_products(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, DB::table('customers')->count(), 'サンプル顧客データが投入されていること');
        $this->assertGreaterThan(0, DB::table('products')->count(), 'サンプル製品データが投入されていること');
    }

    /**
     * 統合テスト1: products.stock_quantity / reserved_quantity が
     * CHECK制約（reserved <= stock）を満たしていることを確認する
     */
    public function test_seeded_products_satisfy_stock_reservation_invariant(): void
    {
        $this->seed();

        $products = DB::table('products')->get();

        $this->assertNotEmpty($products);

        foreach ($products as $product) {
            $this->assertGreaterThanOrEqual(
                $product->reserved_quantity,
                $product->stock_quantity,
                "製品 {$product->product_code} の引当数は実在庫数を超えてはならない"
            );
            $this->assertGreaterThanOrEqual(0, $product->stock_quantity);
            $this->assertGreaterThanOrEqual(0, $product->reserved_quantity);
        }
    }
}
