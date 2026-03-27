<?php

namespace App\Http\Controllers\App;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\IndexTransactionRequest;
use App\Http\Resources\App\TransactionIndexPageResource;
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
        $scope = $this->departmentScopeService->resolveTransactionFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        return Inertia::render('app/Transactions/Index', (new TransactionIndexPageResource([
            'transactions' => $this->transactionRepository->getForIndex($scope['department_id'], $filters),
            'categories' => $this->categoryRepository->getForIndex(),
            'departments' => $this->departmentScopeService->getTransactionOptionsFor($request->user()),
            'type' => $type,
            'category_id' => $filters['category_id'],
            'month' => $filters['month'],
            'year' => $filters['year'],
            'search' => $validated['search'] ?? null,
            'department_scope' => $scope,
            'years' => $this->transactionRepository->getDistinctYears($scope['department_id']),
        ]))->resolve($request));
    }
}
