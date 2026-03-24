<?php

use App\Enums\UserRole;
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
        if (! Schema::hasColumn('users', 'is_system_account')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_system_account')
                    ->default(false)
                    ->after('is_active');
            });
        }

        if (Schema::hasColumn('users', 'is_system_account')) {
            DB::table('users')
                ->where('email', 'superadmin@gmail.com')
                ->update([
                    'role' => UserRole::Admin->value,
                    'is_active' => true,
                    'is_system_account' => true,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_system_account')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_system_account');
            });
        }
    }
};
