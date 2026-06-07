<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TASK-0002 単体テスト要件 テストケース1・2・3 に対応する検証。
 *
 * @see docs/tasks/manufacture-sales-system/TASK-0002.md
 * @see docs/design/manufacture-sales-system/database-schema.sql
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テストケース1: 全マイグレーションが正常に実行される
     */
    public function test_all_tables_are_created_by_migrations(): void
    {
        $tables = [
            'users',
            'customers',
            'products',
            'quotations',
            'quotation_items',
            'sales_orders',
            'sales_order_items',
            'shipments',
            'invoices',
            'payments',
            'stock_movements',
            'document_sequences',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "テーブル {$table} が作成されていません");
        }
    }

    /**
     * テストケース2: テーブル定義がスキーマと一致する（usersの拡張カラム）
     */
    public function test_users_table_has_role_and_is_active_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['role', 'is_active']));

        $userId = DB::table('users')->insertGetId([
            'name' => 'スキーマ確認太郎',
            'email' => 'schema-check@example.com',
            'password' => 'dummy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->find($userId);

        $this->assertSame(2, (int) $user->role, 'roleのデフォルト値は2(営業担当者)であること');
        $this->assertEquals(1, (int) $user->is_active, 'is_activeのデフォルト値はTRUEであること');
    }

    /**
     * テストケース2: テーブル定義がスキーマと一致する（主要カラムの存在確認）
     */
    public function test_table_columns_match_schema_definition(): void
    {
        $this->assertTrue(Schema::hasColumns('customers', [
            'company_name', 'contact_name', 'address', 'phone', 'email', 'credit_limit', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('products', [
            'product_code', 'product_name', 'unit_price', 'unit', 'stock_quantity', 'reserved_quantity', 'alert_threshold',
        ]));

        $this->assertTrue(Schema::hasColumns('quotations', [
            'quotation_number', 'customer_id', 'status', 'remarks', 'expires_at', 'created_by',
        ]));

        $this->assertTrue(Schema::hasColumns('sales_orders', [
            'order_number', 'quotation_id', 'customer_id', 'status', 'confirmed_at', 'cancelled_at', 'created_by',
        ]));

        $this->assertTrue(Schema::hasColumns('invoices', [
            'invoice_number', 'sales_order_id', 'total_amount', 'payment_status', 'invoice_pdf_path', 'issued_at', 'issued_by',
        ]));

        $this->assertTrue(Schema::hasColumns('payments', [
            'invoice_id', 'amount', 'paid_at', 'source', 'raw_csv_row',
        ]));

        $this->assertTrue(Schema::hasColumns('stock_movements', [
            'product_id', 'reason', 'quantity_change', 'related_order_id', 'operated_by', 'memo', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('document_sequences', [
            'document_type', 'fiscal_year', 'last_number',
        ]));
    }

    /**
     * テストケース2: ユニーク制約 uq_document_sequences（document_type, fiscal_year）
     */
    public function test_document_sequences_unique_constraint(): void
    {
        DB::table('document_sequences')->insert([
            'document_type' => 1,
            'fiscal_year' => 2026,
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('document_sequences')->insert([
            'document_type' => 1,
            'fiscal_year' => 2026,
            'last_number' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * テストケース3: CHECK制約により不正な値の登録が拒否される
     *
     * SQLiteは`ALTER TABLE ... ADD CONSTRAINT`によるCHECK制約の事後追加に対応していないため、
     * マイグレーションではMySQLにのみCHECK制約を適用している（README.md トラブルシューティング参照）。
     * そのため、DBレベルのCHECK制約検証はMySQL接続時のみ実施する。
     */
    public function test_check_constraints_reject_invalid_values_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('CHECK制約はMySQL接続時のみDBレベルで適用されるため、MySQL環境でのみ検証する');
        }

        $userId = null;

        try {
            $this->expectException(\Illuminate\Database\QueryException::class);

            $userId = DB::table('users')->insertGetId([
                'name' => '不正ロール太郎',
                'email' => 'invalid-role@example.com',
                'password' => 'dummy',
                'role' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            if ($userId) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }
    }

    /**
     * テストケース3: products.reserved_quantity <= stock_quantity 制約（MySQLのみ）
     */
    public function test_products_reserved_quantity_cannot_exceed_stock_quantity_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('CHECK制約はMySQL接続時のみDBレベルで適用されるため、MySQL環境でのみ検証する');
        }

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('products')->insert([
            'product_code' => 'CHK-0001',
            'product_name' => '在庫整合性確認用製品',
            'unit_price' => 100,
            'unit' => '個',
            'stock_quantity' => 5,
            'reserved_quantity' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
