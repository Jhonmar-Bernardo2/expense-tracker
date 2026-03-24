<?php

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
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
        if (! Schema::hasTable('approval_vouchers')) {
            Schema::create('approval_vouchers', function (Blueprint $table) {
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
                $table->enum('module', array_map(
                    static fn (ApprovalVoucherModule $module) => $module->value,
                    ApprovalVoucherModule::cases(),
                ));
                $table->enum('action', array_map(
                    static fn (ApprovalVoucherAction $action) => $action->value,
                    ApprovalVoucherAction::cases(),
                ));
                $table->enum('status', array_map(
                    static fn (ApprovalVoucherStatus $status) => $status->value,
                    ApprovalVoucherStatus::cases(),
                ))->default(ApprovalVoucherStatus::Draft->value);
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('before_payload')->nullable();
                $table->json('after_payload')->nullable();
                $table->text('remarks')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();

                $table->index(['department_id', 'status']);
                $table->index(['requested_by', 'status']);
                $table->index(['module', 'status']);
                $table->index(['module', 'target_id']);
            });
        }

        $hasTransactionOriginApprovalVoucherId = Schema::hasColumn('transactions', 'origin_approval_voucher_id');
        $hasTransactionVoidedAt = Schema::hasColumn('transactions', 'voided_at');
        $hasTransactionVoidedByApprovalVoucherId = Schema::hasColumn('transactions', 'voided_by_approval_voucher_id');

        if (! $hasTransactionOriginApprovalVoucherId || ! $hasTransactionVoidedAt || ! $hasTransactionVoidedByApprovalVoucherId) {
            Schema::table('transactions', function (Blueprint $table) use (
                $hasTransactionOriginApprovalVoucherId,
                $hasTransactionVoidedAt,
                $hasTransactionVoidedByApprovalVoucherId,
            ) {
                if (! $hasTransactionOriginApprovalVoucherId) {
                    $table->foreignId('origin_approval_voucher_id')
                        ->nullable()
                        ->after('voucher_id')
                        ->constrained('approval_vouchers')
                        ->nullOnDelete();
                }

                if (! $hasTransactionVoidedAt) {
                    $table->timestamp('voided_at')
                        ->nullable()
                        ->after('transaction_date');
                }

                if (! $hasTransactionVoidedByApprovalVoucherId) {
                    $table->foreignId('voided_by_approval_voucher_id')
                        ->nullable()
                        ->after('voided_at')
                        ->constrained('approval_vouchers')
                        ->nullOnDelete();
                }
            });
        }

        $this->safeAddIndex('transactions', ['department_id', 'voided_at']);

        $hasBudgetOriginApprovalVoucherId = Schema::hasColumn('budgets', 'origin_approval_voucher_id');
        $hasBudgetArchivedAt = Schema::hasColumn('budgets', 'archived_at');
        $hasBudgetArchivedByApprovalVoucherId = Schema::hasColumn('budgets', 'archived_by_approval_voucher_id');

        if (! $hasBudgetOriginApprovalVoucherId || ! $hasBudgetArchivedAt || ! $hasBudgetArchivedByApprovalVoucherId) {
            $this->safeDropBudgetPeriodUnique();

            Schema::table('budgets', function (Blueprint $table) use (
                $hasBudgetOriginApprovalVoucherId,
                $hasBudgetArchivedAt,
                $hasBudgetArchivedByApprovalVoucherId,
            ) {
                if (! $hasBudgetOriginApprovalVoucherId) {
                    $table->foreignId('origin_approval_voucher_id')
                        ->nullable()
                        ->after('department_id')
                        ->constrained('approval_vouchers')
                        ->nullOnDelete();
                }

                if (! $hasBudgetArchivedAt) {
                    $table->timestamp('archived_at')
                        ->nullable()
                        ->after('amount_limit');
                }

                if (! $hasBudgetArchivedByApprovalVoucherId) {
                    $table->foreignId('archived_by_approval_voucher_id')
                        ->nullable()
                        ->after('archived_at')
                        ->constrained('approval_vouchers')
                        ->nullOnDelete();
                }
            });
        }

        $this->safeAddIndex(
            'budgets',
            ['department_id', 'category_id', 'year', 'month'],
            'budgets_department_category_period_index',
        );
        $this->safeAddIndex('budgets', ['department_id', 'archived_at']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('budgets')) {
            $this->safeDropIndex('budgets', 'budgets_department_category_period_index');
            $this->safeDropIndex('budgets', ['department_id', 'archived_at']);

            Schema::table('budgets', function (Blueprint $table) {
                if (Schema::hasColumn('budgets', 'archived_by_approval_voucher_id')) {
                    $table->dropConstrainedForeignId('archived_by_approval_voucher_id');
                }

                if (Schema::hasColumn('budgets', 'archived_at')) {
                    $table->dropColumn('archived_at');
                }

                if (Schema::hasColumn('budgets', 'origin_approval_voucher_id')) {
                    $table->dropConstrainedForeignId('origin_approval_voucher_id');
                }
            });

            $this->safeAddUnique('budgets', ['department_id', 'category_id', 'year', 'month']);
        }

        if (Schema::hasTable('transactions')) {
            $this->safeDropIndex('transactions', ['department_id', 'voided_at']);

            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'voided_by_approval_voucher_id')) {
                    $table->dropConstrainedForeignId('voided_by_approval_voucher_id');
                }

                if (Schema::hasColumn('transactions', 'voided_at')) {
                    $table->dropColumn('voided_at');
                }

                if (Schema::hasColumn('transactions', 'origin_approval_voucher_id')) {
                    $table->dropConstrainedForeignId('origin_approval_voucher_id');
                }
            });
        }

        Schema::dropIfExists('approval_vouchers');
    }

    private function safeDropBudgetPeriodUnique(): void
    {
        try {
            Schema::table('budgets', function (Blueprint $table) {
                $table->dropUnique(['department_id', 'category_id', 'year', 'month']);
            });
        } catch (\Throwable) {
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function safeAddIndex(string $tableName, array $columns, ?string $name = null): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $name) {
                if ($name === null) {
                    $table->index($columns);

                    return;
                }

                $table->index($columns, $name);
            });
        } catch (\Throwable) {
        }
    }

    /**
     * @param  list<string>|string  $index
     */
    private function safeDropIndex(string $tableName, array|string $index): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        } catch (\Throwable) {
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function safeAddUnique(string $tableName, array $columns): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->unique($columns);
            });
        } catch (\Throwable) {
        }
    }
};
