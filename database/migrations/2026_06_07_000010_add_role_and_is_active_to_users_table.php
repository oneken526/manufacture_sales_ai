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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('role')->default(2)->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // CHECK制約はSQLiteでALTER TABLE ADD CONSTRAINTがサポートされないため、MySQLのみで適用する
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role BETWEEN 1 AND 4)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_role');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
