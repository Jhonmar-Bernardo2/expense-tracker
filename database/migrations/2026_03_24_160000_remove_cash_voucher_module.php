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
        if (Schema::hasColumn('transactions', 'voucher_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('voucher_id');
            });
        }

        Schema::dropIfExists('voucher_logs');
        Schema::dropIfExists('voucher_attachments');
        Schema::dropIfExists('voucher_items');
        Schema::dropIfExists('vouchers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('Cash voucher module removal cannot be rolled back automatically.');
    }
};
