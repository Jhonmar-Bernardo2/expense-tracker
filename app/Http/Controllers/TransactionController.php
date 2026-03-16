<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\UpsertTransactionRequest;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TransactionRepository;
use App\Services\Transaction\StoreTransactionService;
use App\Services\Transaction\UpdateTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly BudgetRepository $budgetRepository,
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

        return Inertia::render('Transactions/Index', [
            'transactions' => TransactionResource::collection(
                $this->transactionRepository->getForIndex($request->user()->id, $filters)
            ),
            'categories' => CategoryResource::collection(
                $this->categoryRepository->getForIndex($request->user()->id, null)
            ),
            'budgets' => BudgetResource::collection(
                $this->budgetRepository->getForUser($request->user()->id)
            ),
            'filters' => [
                'type' => $type?->value,
                'category' => $filters['category_id'],
                'month' => $filters['month'],
                'year' => $filters['year'],
                'search' => $validated['search'] ?? null,
            ],
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $transactionType) => [
                'value' => $transactionType->value,
                'label' => str($transactionType->value)->headline()->toString(),
            ])->values(),
            'years' => $this->transactionRepository->getDistinctYears($request->user()->id),
        ]);
    }

    public function store(
        UpsertTransactionRequest $request,
        StoreTransactionService $storeTransactionService,
    ): RedirectResponse {
        $storeTransactionService->handle($request->user()->id, $request->validated());

        return back()->with('success', 'Transaction created.');
    }

    public function update(
        UpsertTransactionRequest $request,
        int $transaction,
        UpdateTransactionService $updateTransactionService,
    ): RedirectResponse {
        $existingTransaction = $this->transactionRepository->findForUserOrFail($request->user()->id, $transaction);

        $updateTransactionService->handle($existingTransaction, $request->validated());

        return back()->with('success', 'Transaction updated.');
    }

    public function destroy(Request $request, int $transaction): RedirectResponse
    {
        $existingTransaction = $this->transactionRepository->findForUserOrFail($request->user()->id, $transaction);

        $this->transactionRepository->delete($existingTransaction);

        return back()->with('success', 'Transaction deleted.');
    }
}
