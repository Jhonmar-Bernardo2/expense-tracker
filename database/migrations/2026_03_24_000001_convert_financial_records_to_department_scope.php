<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->restrictOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->restrictOnDelete();
        });

        $departmentByUserId = DB::table('users')
            ->pluck('department_id', 'id')
            ->map(fn (mixed $departmentId) => $departmentId === null ? null : (int) $departmentId);

        $this->backfillBudgetDepartments($departmentByUserId);
        $this->backfillTransactionDepartments($departmentByUserId);

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'category_id', 'year', 'month']);
        });

        $this->mergeGlobalCategories();
        $this->mergeDepartmentBudgets();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type', 'name']);
            $table->dropIndex(['user_id', 'type']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
            $table->unique(['department_id', 'category_id', 'year', 'month']);
            $table->index(['department_id', 'year', 'month']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
            $table->index(['department_id', 'transaction_date']);
            $table->index(['department_id', 'type', 'transaction_date']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['type', 'name']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('The department funds migration is irreversible.');
    }

    /**
     * @param  Collection<int, int|null>  $departmentByUserId
     */
    private function backfillBudgetDepartments(Collection $departmentByUserId): void
    {
        DB::table('budgets')
            ->orderBy('id')
            ->chunkById(250, function (Collection $budgets) use ($departmentByUserId): void {
                foreach ($budgets as $budget) {
                    $departmentId = $departmentByUserId->get((int) $budget->user_id);

                    if ($departmentId === null) {
                        continue;
                    }

                    DB::table('budgets')
                        ->where('id', $budget->id)
                        ->update(['department_id' => $departmentId]);
                }
            });
    }

    /**
     * @param  Collection<int, int|null>  $departmentByUserId
     */
    private function backfillTransactionDepartments(Collection $departmentByUserId): void
    {
        DB::table('transactions')
            ->orderBy('id')
            ->chunkById(250, function (Collection $transactions) use ($departmentByUserId): void {
                foreach ($transactions as $transaction) {
                    $departmentId = $departmentByUserId->get((int) $transaction->user_id);

                    if ($departmentId === null) {
                        continue;
                    }

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update(['department_id' => $departmentId]);
                }
            });
    }

    private function mergeGlobalCategories(): void
    {
        $categories = DB::table('categories')
            ->orderBy('id')
            ->get(['id', 'name', 'type']);

        $canonicalByKey = [];
        $duplicateIds = [];

        foreach ($categories as $category) {
            $normalizedName = Str::lower(Str::squish((string) $category->name));
            $key = "{$category->type}|{$normalizedName}";
            $canonicalId = $canonicalByKey[$key] ?? null;

            if ($canonicalId === null) {
                $canonicalByKey[$key] = (int) $category->id;

                DB::table('categories')
                    ->where('id', $category->id)
                    ->update(['name' => Str::squish((string) $category->name)]);

                continue;
            }

            DB::table('transactions')
                ->where('category_id', $category->id)
                ->update(['category_id' => $canonicalId]);

            DB::table('budgets')
                ->where('category_id', $category->id)
                ->update(['category_id' => $canonicalId]);

            $duplicateIds[] = (int) $category->id;
        }

        if ($duplicateIds !== []) {
            DB::table('categories')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }
    }

    private function mergeDepartmentBudgets(): void
    {
        $budgets = DB::table('budgets')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->get([
                'id',
                'user_id',
                'department_id',
                'category_id',
                'month',
                'year',
                'amount_limit',
            ]);

        $canonicalByKey = [];
        $duplicateIds = [];

        foreach ($budgets as $budget) {
            $key = implode('|', [
                (int) $budget->department_id,
                (int) $budget->category_id,
                (int) $budget->year,
                (int) $budget->month,
            ]);

            $canonicalId = $canonicalByKey[$key] ?? null;

            if ($canonicalId === null) {
                $canonicalByKey[$key] = (int) $budget->id;
                continue;
            }

            DB::table('budgets')
                ->where('id', $canonicalId)
                ->update([
                    'amount_limit' => DB::raw('amount_limit + '.(float) $budget->amount_limit),
                ]);

            $duplicateIds[] = (int) $budget->id;
        }

        if ($duplicateIds !== []) {
            DB::table('budgets')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }
    }
};
