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
    public function getForIndex(int $userId, ?TransactionType $type = null): Collection
    {
        return Category::query()
            ->where('user_id', $userId)
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->withCount(['transactions', 'budgets'])
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function findForUserOrFail(int $userId, int $categoryId): Category
    {
        return Category::query()
            ->where('user_id', $userId)
            ->findOrFail($categoryId);
    }

    /**
     * @return Collection<int, Category>
     */
    public function getExpenseOptions(int $userId): Collection
    {
        return Category::query()
            ->where('user_id', $userId)
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
    public function createForUser(int $userId, array $data): Category
    {
        $payload = $this->normalizePayload($data);

        return Category::query()->create([
            'user_id' => $userId,
            'name' => $payload['name'],
            'type' => $payload['type'],
        ]);
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
        return $category->transactions()->exists() || $category->budgets()->exists();
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
