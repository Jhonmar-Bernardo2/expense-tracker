<?php

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoAttachmentKind;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
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
        if (! Schema::hasTable('approval_memos')) {
            Schema::create('approval_memos', function (Blueprint $table) {
                $table->id();
                $table->string('memo_no')->unique();
                $table->foreignId('department_id')
                    ->constrained()
                    ->restrictOnDelete();
                $table->foreignId('requested_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->enum('module', array_map(
                    static fn (ApprovalVoucherModule $module) => $module->value,
                    ApprovalVoucherModule::cases(),
                ));
                $table->enum('action', array_map(
                    static fn (ApprovalMemoAction $action) => $action->value,
                    ApprovalMemoAction::cases(),
                ));
                $table->enum('status', array_map(
                    static fn (ApprovalMemoStatus $status) => $status->value,
                    ApprovalMemoStatus::cases(),
                ))->default(ApprovalMemoStatus::Draft->value);
                $table->text('remarks')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['department_id', 'status']);
                $table->index(['requested_by', 'status']);
                $table->index(['module', 'status']);
                $table->index(['action', 'status']);
            });
        }

        if (! Schema::hasTable('approval_memo_attachments')) {
            Schema::create('approval_memo_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_memo_id')
                    ->constrained('approval_memos')
                    ->cascadeOnDelete();
                $table->foreignId('uploaded_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->enum('kind', array_map(
                    static fn (ApprovalMemoAttachmentKind $kind) => $kind->value,
                    ApprovalMemoAttachmentKind::cases(),
                ));
                $table->string('original_name');
                $table->string('disk');
                $table->string('path');
                $table->string('mime_type');
                $table->unsignedBigInteger('size_bytes');
                $table->timestamps();

                $table->index(['approval_memo_id', 'kind']);
            });
        }

        if (! Schema::hasColumn('approval_vouchers', 'approval_memo_id')) {
            Schema::table('approval_vouchers', function (Blueprint $table) {
                $table->foreignId('approval_memo_id')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('approval_memos')
                    ->nullOnDelete()
                    ->unique();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('approval_vouchers') && Schema::hasColumn('approval_vouchers', 'approval_memo_id')) {
            Schema::table('approval_vouchers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('approval_memo_id');
            });
        }

        Schema::dropIfExists('approval_memo_attachments');
        Schema::dropIfExists('approval_memos');
    }
};
