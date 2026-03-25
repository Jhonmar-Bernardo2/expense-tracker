<?php

use App\Enums\ApprovalVoucherAttachmentKind;
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
        Schema::table('approval_voucher_attachments', function (Blueprint $table) {
            $table->string('kind', 50)
                ->default(ApprovalVoucherAttachmentKind::SupportingDocument->value)
                ->after('uploaded_by');

            $table->index(['approval_voucher_id', 'kind']);
        });

        DB::table('approval_voucher_attachments')
            ->whereNull('kind')
            ->update([
                'kind' => ApprovalVoucherAttachmentKind::SupportingDocument->value,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_voucher_attachments', function (Blueprint $table) {
            $table->dropIndex(['approval_voucher_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
