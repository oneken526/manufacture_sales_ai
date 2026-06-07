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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('document_type');
            $table->integer('fiscal_year');
            $table->integer('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'fiscal_year'], 'uq_document_sequences');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_sequences ADD CONSTRAINT chk_document_sequences_type CHECK (document_type BETWEEN 1 AND 3)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_sequences DROP CONSTRAINT chk_document_sequences_type');
        }

        Schema::dropIfExists('document_sequences');
    }
};
