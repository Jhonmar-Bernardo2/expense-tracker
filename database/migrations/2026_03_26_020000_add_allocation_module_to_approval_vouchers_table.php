<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('approval_vouchers')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE approval_vouchers DROP CONSTRAINT IF EXISTS approval_vouchers_module_check');
            DB::statement(
                "ALTER TABLE approval_vouchers
                ADD CONSTRAINT approval_vouchers_module_check
                CHECK (module IN ('transaction', 'budget', 'allocation'))"
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE approval_vouchers
                MODIFY module ENUM('transaction', 'budget', 'allocation') NOT NULL"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('approval_vouchers')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE approval_vouchers DROP CONSTRAINT IF EXISTS approval_vouchers_module_check');
            DB::statement(
                "ALTER TABLE approval_vouchers
                ADD CONSTRAINT approval_vouchers_module_check
                CHECK (module IN ('transaction', 'budget'))"
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE approval_vouchers
                MODIFY module ENUM('transaction', 'budget') NOT NULL"
            );
        }
    }
};
