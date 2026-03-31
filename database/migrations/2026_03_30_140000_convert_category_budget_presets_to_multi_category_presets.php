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

        if (!Schema::hasTable('category_budget_preset_items')) {
            Schema::create('category_budget_preset_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_budget_preset_id')
                    ->constrained('category_budget_presets')
                    ->cascadeOnDelete();
                $table->foreignId('category_id')
                    ->constrained()
                    ->restrictOnDelete();
                $table->decimal('amount_limit', 12, 2);
                $table->timestamps();

                $table->unique(['category_budget_preset_id', 'category_id']);
            });
        }

        if (
            Schema::hasColumn('category_budget_presets', 'category_id')
            && Schema::hasColumn('category_budget_presets', 'amount_limit')
        ) {
            $legacyPresets = DB::table('category_budget_presets')
                ->select([
                    'id',
                    'name',
                    'category_id',
                    'amount_limit',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('id')
                ->get();

            $headerIdsByName = [];
            $duplicateHeaderIds = [];

            foreach ($legacyPresets as $legacyPreset) {
                $presetName = trim((string) $legacyPreset->name);

                if ($presetName === '') {
                    $presetName = sprintf('Preset %d', $legacyPreset->id);

                    DB::table('category_budget_presets')
                        ->where('id', $legacyPreset->id)
                        ->update(['name' => $presetName]);
                }

                if (!isset($headerIdsByName[$presetName])) {
                    $headerIdsByName[$presetName] = (int) $legacyPreset->id;
                }

                $headerId = $headerIdsByName[$presetName];

                DB::table('category_budget_preset_items')->updateOrInsert(
                    [
                        'category_budget_preset_id' => $headerId,
                        'category_id' => $legacyPreset->category_id,
                    ],
                    [
                        'amount_limit' => $legacyPreset->amount_limit,
                        'created_at' => $legacyPreset->created_at,
                        'updated_at' => $legacyPreset->updated_at,
                    ],
                );

                if ($headerId !== (int) $legacyPreset->id) {
                    $duplicateHeaderIds[] = (int) $legacyPreset->id;
                }
            }

            if ($duplicateHeaderIds !== []) {
                DB::table('category_budget_presets')
                    ->whereIn('id', array_values(array_unique($duplicateHeaderIds)))
                    ->delete();
            }

            DB::statement(
                'ALTER TABLE category_budget_presets DROP CONSTRAINT IF EXISTS category_budget_presets_category_id_name_unique',
            );
            DB::statement(
                'ALTER TABLE category_budget_presets DROP CONSTRAINT IF EXISTS category_budget_presets_category_id_unique',
            );
            DB::statement(
                'ALTER TABLE category_budget_presets DROP CONSTRAINT IF EXISTS category_budget_presets_category_id_foreign',
            );
            DB::statement(
                'ALTER TABLE category_budget_presets DROP COLUMN IF EXISTS category_id',
            );
            DB::statement(
                'ALTER TABLE category_budget_presets DROP COLUMN IF EXISTS amount_limit',
            );
        }

        if (! $this->constraintExists('category_budget_presets_name_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->unique('name');
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

        if (
            !Schema::hasColumn('category_budget_presets', 'category_id')
            && Schema::hasTable('category_budget_preset_items')
        ) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained()
                    ->restrictOnDelete();
                $table->decimal('amount_limit', 12, 2)->nullable();
            });

            $presets = DB::table('category_budget_presets')
                ->select(['id', 'name', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->get();

            foreach ($presets as $preset) {
                $items = DB::table('category_budget_preset_items')
                    ->where('category_budget_preset_id', $preset->id)
                    ->orderBy('id')
                    ->get();

                $firstItem = $items->shift();

                if ($firstItem !== null) {
                    DB::table('category_budget_presets')
                        ->where('id', $preset->id)
                        ->update([
                            'category_id' => $firstItem->category_id,
                            'amount_limit' => $firstItem->amount_limit,
                        ]);
                }

                foreach ($items as $item) {
                    DB::table('category_budget_presets')->insert([
                        'name' => $preset->name,
                        'category_id' => $item->category_id,
                        'amount_limit' => $item->amount_limit,
                        'created_at' => $preset->created_at,
                        'updated_at' => $preset->updated_at,
                    ]);
                }
            }
        }

        if ($this->constraintExists('category_budget_presets_name_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->dropUnique('category_budget_presets_name_unique');
            });
        }

        if ($this->constraintExists('category_budget_presets_category_id_name_unique')) {
            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->dropUnique('category_budget_presets_category_id_name_unique');
            });
        }

        if (Schema::hasColumn('category_budget_presets', 'category_id')) {
            DB::statement(
                'ALTER TABLE category_budget_presets ALTER COLUMN category_id SET NOT NULL',
            );
            DB::statement(
                'ALTER TABLE category_budget_presets ALTER COLUMN amount_limit SET NOT NULL',
            );

            Schema::table('category_budget_presets', function (Blueprint $table) {
                $table->unique(['category_id', 'name']);
            });
        }

        Schema::dropIfExists('category_budget_preset_items');
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
