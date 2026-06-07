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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->bigInteger('amount');
            $table->date('paid_at');
            $table->unsignedTinyInteger('source')->default(1);
            $table->text('raw_csv_row')->nullable();
            $table->timestamps();

            $table->index('invoice_id', 'idx_payments_invoice_id');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_source CHECK (source BETWEEN 1 AND 2)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_payments_source');
        }

        Schema::dropIfExists('payments');
    }
};
