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
        if (Schema::hasTable('approval_vouchers') && Schema::hasColumn('approval_vouchers', 'approval_memo_id')) {
            try {
                Schema::table('approval_vouchers', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('approval_memo_id');
                });
            } catch (\Throwable) {
                Schema::table('approval_vouchers', function (Blueprint $table) {
                    $table->dropColumn('approval_memo_id');
                });
            }
        }

        Schema::dropIfExists('approval_memo_attachments');
        Schema::dropIfExists('approval_memos');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
