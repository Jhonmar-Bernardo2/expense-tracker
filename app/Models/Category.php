<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function budgetPresetItems(): HasMany
    {
        return $this->hasMany(CategoryBudgetPresetItem::class);
    }

    public function budgetPresets(): BelongsToMany
    {
        return $this->belongsToMany(CategoryBudgetPreset::class, 'category_budget_preset_items')
            ->withPivot(['id', 'category_id', 'amount_limit'])
            ->withTimestamps();
    }
}
