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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 50)->unique();
            $table->string('product_name');
            $table->bigInteger('unit_price');
            $table->string('unit', 20)->default('個');
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('alert_threshold')->default(0);
            $table->timestamps();

            $table->index('product_code', 'idx_products_product_code');
            $table->index('product_name', 'idx_products_product_name');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock CHECK (stock_quantity >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_reserved CHECK (reserved_quantity >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_reserved_le_stock CHECK (reserved_quantity <= stock_quantity)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_stock');
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_reserved');
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_reserved_le_stock');
        }

        Schema::dropIfExists('products');
    }
};
