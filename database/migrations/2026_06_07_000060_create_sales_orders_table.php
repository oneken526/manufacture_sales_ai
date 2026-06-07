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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations');
            $table->foreignId('customer_id')->constrained('customers');
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('customer_id', 'idx_sales_orders_customer_id');
            $table->index('status', 'idx_sales_orders_status');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT chk_sales_orders_status CHECK (status BETWEEN 1 AND 6)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sales_orders DROP CONSTRAINT chk_sales_orders_status');
        }

        Schema::dropIfExists('sales_orders');
    }
};
