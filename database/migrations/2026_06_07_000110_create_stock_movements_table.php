<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedTinyInteger('reason');
            $table->integer('quantity_change');
            $table->foreignId('related_order_id')->nullable()->constrained('sales_orders');
            $table->foreignId('operated_by')->constrained('users');
            $table->string('memo', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('product_id', 'idx_stock_movements_product_id');
            $table->index('created_at', 'idx_stock_movements_created_at');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_reason CHECK (reason BETWEEN 1 AND 5)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT chk_stock_movements_reason');
        }

        Schema::dropIfExists('stock_movements');
    }
};
