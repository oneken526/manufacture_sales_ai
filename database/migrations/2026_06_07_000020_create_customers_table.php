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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->bigInteger('credit_limit')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_name', 'idx_customers_company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
