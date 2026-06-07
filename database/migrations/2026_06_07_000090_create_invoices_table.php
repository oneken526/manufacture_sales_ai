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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('sales_order_id')->unique()->constrained('sales_orders');
            $table->bigInteger('total_amount');
            $table->unsignedTinyInteger('payment_status')->default(1);
            $table->string('invoice_pdf_path', 500)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamps();

            $table->index('payment_status', 'idx_invoices_payment_status');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_payment_status CHECK (payment_status BETWEEN 1 AND 3)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_invoices_payment_status');
        }

        Schema::dropIfExists('invoices');
    }
};
