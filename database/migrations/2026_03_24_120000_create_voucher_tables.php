<?php

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
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
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('voucher_no')->unique();
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
                $table->foreignId('released_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('liquidation_reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->enum('type', array_map(
                    static fn (VoucherType $type) => $type->value,
                    VoucherType::cases(),
                ));
                $table->enum('status', array_map(
                    static fn (VoucherStatus $status) => $status->value,
                    VoucherStatus::cases(),
                ))->default(VoucherStatus::Draft->value);
                $table->string('purpose');
                $table->text('remarks')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('liquidation_return_reason')->nullable();
                $table->decimal('requested_amount', 12, 2);
                $table->decimal('approved_amount', 12, 2)->nullable();
                $table->decimal('released_amount', 12, 2)->nullable();
                $table->date('liquidation_due_date')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('liquidation_submitted_at')->nullable();
                $table->timestamp('liquidation_reviewed_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->index(['department_id', 'status']);
                $table->index(['requested_by', 'status']);
            });
        }

        if (! Schema::hasTable('voucher_items')) {
            Schema::create('voucher_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('category_id')
                    ->constrained()
                    ->restrictOnDelete();
                $table->string('description');
                $table->decimal('amount', 12, 2);
                $table->date('expense_date');
                $table->timestamps();

                $table->index(['voucher_id', 'expense_date']);
            });
        }

        if (! Schema::hasTable('voucher_attachments')) {
            Schema::create('voucher_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voucher_logs')) {
            Schema::create('voucher_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
                $table->string('action');
                $table->enum('from_status', array_map(
                    static fn (VoucherStatus $status) => $status->value,
                    VoucherStatus::cases(),
                ))->nullable();
                $table->enum('to_status', array_map(
                    static fn (VoucherStatus $status) => $status->value,
                    VoucherStatus::cases(),
                ))->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['voucher_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('transactions', 'voucher_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('voucher_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
};
