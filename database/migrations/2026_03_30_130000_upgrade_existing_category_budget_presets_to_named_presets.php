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
        if (!Schema::hasTable('category_budget_presets')) {
            return;
        }

        if (!Schema::hasColumn('category_budget_presets', 'name')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->string('name')->nullable();
            });
        }

        // Backfill legacy single-preset rows so the new required name column is valid.
        DB::table('category_budget_presets')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $preset): void {
                if (trim((string) ($preset->name ?? '')) !== '') {
                    return;
                }

                DB::table('category_budget_presets')
                    ->where('id', $preset->id)
                    ->update([
                        'name' => sprintf('Preset %d', $preset->id),
                    ]);
            });

        DB::statement(
            'ALTER TABLE category_budget_presets ALTER COLUMN name SET NOT NULL',
        );

        if ($this->constraintExists('category_budget_presets_category_id_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->dropUnique('category_budget_presets_category_id_unique');
            });
        }

        if (! $this->constraintExists('category_budget_presets_category_id_name_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->unique(['category_id', 'name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('category_budget_presets')) {
            return;
        }

        if ($this->constraintExists('category_budget_presets_category_id_name_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->dropUnique('category_budget_presets_category_id_name_unique');
            });
        }

        if (Schema::hasColumn('category_budget_presets', 'name')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }

        if (! $this->constraintExists('category_budget_presets_category_id_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->unique('category_id');
            });
        }
    }

    private function constraintExists(string $constraintName): bool
    {
        $result = DB::selectOne(
            'select exists (select 1 from pg_constraint where conname = ?) as exists',
            [$constraintName],
        );

        return (bool) ($result->exists ?? false);
    }
};
