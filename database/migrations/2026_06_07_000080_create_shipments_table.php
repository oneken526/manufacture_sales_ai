<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders');
            $table->timestamp('shipped_at')->nullable();
            $table->string('delivery_note_path', 500)->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('sales_order_id', 'idx_shipments_sales_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
