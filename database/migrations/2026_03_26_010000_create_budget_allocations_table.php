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
        if (! Schema::hasTable('budget_allocations')) {
            Schema::create('budget_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('department_id')
                    ->constrained()
                    ->restrictOnDelete();
                $table->foreignId('origin_approval_voucher_id')
                    ->nullable()
                    ->constrained('approval_vouchers')
                    ->nullOnDelete();
                $table->unsignedTinyInteger('month');
                $table->unsignedSmallInteger('year');
                $table->decimal('amount_limit', 12, 2);
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('archived_by_approval_voucher_id')
                    ->nullable()
                    ->constrained('approval_vouchers')
                    ->nullOnDelete();
                $table->timestamps();

                $table->index(['department_id', 'year', 'month']);
                $table->index(['department_id', 'archived_at']);
            });
        }

        $departmentId = $this->resolveFinancialManagementDepartmentId();

        if ($departmentId === null || ! Schema::hasTable('budgets')) {
            return;
        }

        $groupedBudgets = DB::table('budgets')
            ->selectRaw('MIN(user_id) as user_id, month, year, SUM(amount_limit) as amount_limit')
            ->whereNull('archived_at')
            ->where('department_id', $departmentId)
            ->groupBy('month', 'year')
            ->get();

        foreach ($groupedBudgets as $groupedBudget) {
            $exists = DB::table('budget_allocations')
                ->where('department_id', $departmentId)
                ->where('month', (int) $groupedBudget->month)
                ->where('year', (int) $groupedBudget->year)
                ->whereNull('archived_at')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('budget_allocations')->insert([
                'user_id' => (int) $groupedBudget->user_id,
                'department_id' => $departmentId,
                'origin_approval_voucher_id' => null,
                'month' => (int) $groupedBudget->month,
                'year' => (int) $groupedBudget->year,
                'amount_limit' => round((float) $groupedBudget->amount_limit, 2),
                'archived_at' => null,
                'archived_by_approval_voucher_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
    }

    private function resolveFinancialManagementDepartmentId(): ?int
    {
        if (! Schema::hasTable('departments')) {
            return null;
        }

        $departmentId = DB::table('departments')
            ->where('is_financial_management', true)
            ->orderBy('id')
            ->value('id');

        if ($departmentId !== null) {
            return (int) $departmentId;
        }

        $departmentId = DB::table('departments')
            ->whereRaw('LOWER(name) = ?', ['financial management'])
            ->orderBy('id')
            ->value('id');

        if ($departmentId !== null) {
            DB::table('departments')
                ->where('id', $departmentId)
                ->update([
                    'is_financial_management' => true,
                    'is_locked' => true,
                    'updated_at' => now(),
                ]);

            return (int) $departmentId;
        }

        return (int) DB::table('departments')->insertGetId([
            'name' => 'Financial Management',
            'description' => 'Central budget department.',
            'is_financial_management' => true,
            'is_locked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
