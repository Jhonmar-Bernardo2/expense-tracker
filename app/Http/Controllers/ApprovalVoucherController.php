<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Enums\TransactionType;
use App\Http\Requests\ApproveApprovalVoucherRequest;
use App\Http\Requests\IndexApprovalVoucherRequest;
use App\Http\Requests\RejectApprovalVoucherRequest;
use App\Http\Requests\SubmitApprovalVoucherRequest;
use App\Http\Requests\UpsertApprovalVoucherRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ApprovalVoucherResource;
use App\Models\Department;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ApprovalVoucherRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DepartmentRepository;
use App\Services\ApprovalVoucher\ApproveApprovalVoucherService;
use App\Services\ApprovalVoucher\RejectApprovalVoucherService;
use App\Services\ApprovalVoucher\StoreApprovalVoucherService;
use App\Services\ApprovalVoucher\SubmitApprovalVoucherService;
use App\Services\ApprovalVoucher\UpdateApprovalVoucherService;
use App\Services\Department\DepartmentScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalVoucherController extends Controller
{
    public function __construct(
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DepartmentRepository $departmentRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {}

    public function index(IndexApprovalVoucherRequest $request): Response
    {
        $validated = $request->validated();
        $scope = $this->resolveApprovalVoucherScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        $filters = [
            'status' => isset($validated['status'])
                ? ApprovalVoucherStatus::from($validated['status'])
                : null,
            'module' => isset($validated['module'])
                ? ApprovalVoucherModule::from($validated['module'])
                : null,
            'action' => isset($validated['action'])
                ? ApprovalVoucherAction::from($validated['action'])
                : null,
            'search' => $validated['search'] ?? null,
        ];

        return Inertia::render('ApprovalVouchers/Index', [
            'approval_vouchers' => ApprovalVoucherResource::collection(
                $this->approvalVoucherRepository->getForIndex($request->user(), $scope['department_id'], $filters)
            ),
            'departments' => $this->getApprovalVoucherDepartmentOptions($request->user())
                ->map(fn ($department) => $department->toSummaryArray())
                ->values(),
            'department_scope' => $scope,
            'filters' => [
                'department' => $scope['department_id'],
                'status' => $filters['status']?->value,
                'module' => $filters['module']?->value,
                'action' => $filters['action']?->value,
                'search' => $filters['search'],
            ],
            'statuses' => collect(ApprovalVoucherStatus::cases())->map(fn (ApprovalVoucherStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'modules' => collect(ApprovalVoucherModule::cases())->map(fn (ApprovalVoucherModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
            ])->values(),
            'actions' => collect(ApprovalVoucherAction::cases())->map(fn (ApprovalVoucherAction $action) => [
                'value' => $action->value,
                'label' => $action->label(),
            ])->values(),
        ]);
    }

    public function show(Request $request, int $approvalVoucher): Response
    {
        $approvalVoucher = $this->approvalVoucherRepository->findForViewerOrFail($request->user(), $approvalVoucher);

        return Inertia::render('ApprovalVouchers/Show', [
            'approval_voucher' => new ApprovalVoucherResource($approvalVoucher),
            'activity_logs' => ActivityLogResource::collection(
                $this->activityLogRepository->getTimelineForApprovalVoucher($approvalVoucher)
            ),
            'categories' => $this->categoryRepository->getForIndex()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                ])
                ->values(),
            'departments' => $this->getApprovalVoucherDepartmentOptions($request->user())
                ->map(fn ($department) => $department->toSummaryArray())
                ->values(),
            'transaction_types' => collect(TransactionType::cases())->map(fn (TransactionType $type) => [
                'value' => $type->value,
                'label' => str($type->value)->headline()->toString(),
            ])->values(),
        ]);
    }

    public function print(Request $request, int $approvalVoucher): Response
    {
        return Inertia::render('ApprovalVouchers/Print', [
            'approval_voucher' => new ApprovalVoucherResource(
                $this->approvalVoucherRepository->findForViewerOrFail($request->user(), $approvalVoucher)
            ),
            'categories' => $this->categoryRepository->getForIndex()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                ])
                ->values(),
            'departments' => $this->getApprovalVoucherDepartmentOptions($request->user())
                ->map(fn ($department) => $department->toSummaryArray())
                ->values(),
        ]);
    }

    public function store(
        UpsertApprovalVoucherRequest $request,
        StoreApprovalVoucherService $storeApprovalVoucherService,
    ): RedirectResponse {
        $approvalVoucher = $storeApprovalVoucherService->handle($request->user(), $request->validated());

        return to_route('approval-vouchers.show', $approvalVoucher)
            ->with(
                'success',
                $approvalVoucher->status === ApprovalVoucherStatus::PendingApproval
                    ? 'Request sent for review.'
                    : 'Draft request created.',
            );
    }

    public function update(
        UpsertApprovalVoucherRequest $request,
        int $approvalVoucher,
        UpdateApprovalVoucherService $updateApprovalVoucherService,
    ): RedirectResponse {
        $existingApprovalVoucher = $this->approvalVoucherRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalVoucher,
        );

        $updateApprovalVoucherService->handle(
            $request->user(),
            $existingApprovalVoucher,
            $request->validated(),
        );

        return back()->with('success', 'Request updated.');
    }

    public function submit(
        SubmitApprovalVoucherRequest $request,
        int $approvalVoucher,
        SubmitApprovalVoucherService $submitApprovalVoucherService,
    ): RedirectResponse {
        $existingApprovalVoucher = $this->approvalVoucherRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalVoucher,
        );

        $submitApprovalVoucherService->handle($request->user(), $existingApprovalVoucher);

        return back()->with('success', 'Request sent for review.');
    }

    public function approve(
        ApproveApprovalVoucherRequest $request,
        int $approvalVoucher,
        ApproveApprovalVoucherService $approveApprovalVoucherService,
    ): RedirectResponse {
        $existingApprovalVoucher = $this->approvalVoucherRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalVoucher,
        );

        $approveApprovalVoucherService->handle(
            $request->user(),
            $existingApprovalVoucher,
            $request->validated(),
        );

        return back()->with('success', 'Request approved and applied.');
    }

    public function reject(
        RejectApprovalVoucherRequest $request,
        int $approvalVoucher,
        RejectApprovalVoucherService $rejectApprovalVoucherService,
    ): RedirectResponse {
        $existingApprovalVoucher = $this->approvalVoucherRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalVoucher,
        );

        $rejectApprovalVoucherService->handle(
            $request->user(),
            $existingApprovalVoucher,
            $request->validated(),
        );

        return back()->with('success', 'Request rejected.');
    }

    /**
     * @return array{
     *     department_id: int|null,
     *     selected_department: array{id: int, name: string, is_financial_management: bool, is_locked: bool}|null,
     *     can_select_department: bool,
     *     is_all_departments: bool
     * }
     */
    private function resolveApprovalVoucherScope(User $user, ?int $requestedDepartmentId): array
    {
        if (! ($user->isAdmin() || $user->isFinancialManagement())) {
            return $this->departmentScopeService->resolveFilterScope($user, $requestedDepartmentId);
        }

        $department = $requestedDepartmentId === null
            ? null
            : $this->departmentRepository->findOrFail($requestedDepartmentId);

        return [
            'department_id' => $department?->id,
            'selected_department' => $department?->toSummaryArray(),
            'can_select_department' => true,
            'is_all_departments' => $department === null,
        ];
    }

    /**
     * @return Collection<int, Department>
     */
    private function getApprovalVoucherDepartmentOptions(User $user)
    {
        if ($user->isAdmin() || $user->isFinancialManagement()) {
            return $this->departmentRepository->getOptions();
        }

        return $this->departmentScopeService->getOptionsFor($user);
    }
}
