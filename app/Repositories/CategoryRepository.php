<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    /**
     * @return Collection<int, Category>
     */
    public function getForIndex(?TransactionType $type = null): Collection
    {
        return Category::query()
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->withCount(['transactions', 'budgets', 'voucherItems'])
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function findOrFail(int $categoryId): Category
    {
        return Category::query()->findOrFail($categoryId);
    }

    /**
     * @return Collection<int, Category>
     */
    public function getExpenseOptions(): Collection
    {
        return Category::query()
            ->where('type', TransactionType::Expense->value)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'type',
            ]);
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function create(array $data): Category
    {
        $payload = $this->normalizePayload($data);

        return Category::query()->create($payload);
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function update(Category $category, array $data): Category
    {
        $payload = $this->normalizePayload($data);

        $category->update([
            'name' => $payload['name'],
            'type' => $payload['type'],
        ]);

        return $category->refresh();
    }

    public function hasRelatedRecords(Category $category): bool
    {
        return $category->transactions()->exists()
            || $category->budgets()->exists()
            || $category->voucherItems()->exists();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    /**
     * @param  array{name: string, type: string}  $data
     * @return array{name: string, type: string}
     */
    private function normalizePayload(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'type' => $data['type'],
        ];
    }
}
