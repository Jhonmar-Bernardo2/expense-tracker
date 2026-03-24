<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Repositories\CategoryRepository;
use App\Repositories\TransactionRepository;
use App\Services\Department\DepartmentScopeService;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {
    }

    public function index(IndexTransactionRequest $request): Response
    {
        $validated = $request->validated();

        $type = isset($validated['type'])
            ? TransactionType::from($validated['type'])
            : null;

        $filters = [
            'type' => $type,
            'category_id' => isset($validated['category']) ? (int) $validated['category'] : null,
            'month' => isset($validated['month']) ? (int) $validated['month'] : null,
            'year' => isset($validated['year']) ? (int) $validated['year'] : null,
            'search' => $validated['search'] ?? null,
        ];
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        return Inertia::render('Transactions/Index', [
            'transactions' => TransactionResource::collection(
                $this->transactionRepository->getForIndex($scope['department_id'], $filters)
            ),
            'categories' => CategoryResource::collection(
                $this->categoryRepository->getForIndex()
            ),
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->values(),
            'filters' => [
                'type' => $type?->value,
                'category' => $filters['category_id'],
                'month' => $filters['month'],
                'year' => $filters['year'],
                'search' => $validated['search'] ?? null,
                'department' => $scope['department_id'],
            ],
            'department_scope' => $scope,
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $transactionType) => [
                'value' => $transactionType->value,
                'label' => str($transactionType->value)->headline()->toString(),
            ])->values(),
            'years' => $this->transactionRepository->getDistinctYears($scope['department_id']),
        ]);
    }

}
