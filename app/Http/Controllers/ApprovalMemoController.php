<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
use App\Http\Requests\ApproveApprovalMemoRequest;
use App\Http\Requests\IndexApprovalMemoRequest;
use App\Http\Requests\RejectApprovalMemoRequest;
use App\Http\Requests\SubmitApprovalMemoRequest;
use App\Http\Requests\UpsertApprovalMemoRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ApprovalMemoResource;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ApprovalMemoRepository;
use App\Services\ApprovalMemo\ApproveApprovalMemoService;
use App\Services\ApprovalMemo\DeleteApprovalMemoService;
use App\Services\ApprovalMemo\RejectApprovalMemoService;
use App\Services\ApprovalMemo\StoreApprovalMemoService;
use App\Services\ApprovalMemo\SubmitApprovalMemoService;
use App\Services\ApprovalMemo\UpdateApprovalMemoService;
use App\Services\Department\DepartmentScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalMemoController extends Controller
{
    public function __construct(
        private readonly ApprovalMemoRepository $approvalMemoRepository,
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {}

    public function index(IndexApprovalMemoRequest $request): Response
    {
        $validated = $request->validated();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        $filters = [
            'status' => isset($validated['status'])
                ? ApprovalMemoStatus::from($validated['status'])
                : null,
            'module' => isset($validated['module'])
                ? ApprovalVoucherModule::from($validated['module'])
                : null,
            'action' => isset($validated['action'])
                ? ApprovalMemoAction::from($validated['action'])
                : null,
            'search' => $validated['search'] ?? null,
        ];

        return Inertia::render('ApprovalMemos/Index', [
            'approval_memos' => ApprovalMemoResource::collection(
                $this->approvalMemoRepository->getForIndex($request->user(), $scope['department_id'], $filters)
            ),
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->values(),
            'department_scope' => $scope,
            'filters' => [
                'department' => $scope['department_id'],
                'status' => $filters['status']?->value,
                'module' => $filters['module']?->value,
                'action' => $filters['action']?->value,
                'search' => $filters['search'],
            ],
            'statuses' => collect(ApprovalMemoStatus::cases())->map(fn (ApprovalMemoStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'modules' => collect(ApprovalVoucherModule::cases())->map(fn (ApprovalVoucherModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
            ])->values(),
            'actions' => collect(ApprovalMemoAction::cases())->map(fn (ApprovalMemoAction $action) => [
                'value' => $action->value,
                'label' => $action->label(),
            ])->values(),
        ]);
    }

    public function show(Request $request, int $approvalMemo): Response
    {
        $approvalMemo = $this->approvalMemoRepository->findForViewerOrFail($request->user(), $approvalMemo);

        return Inertia::render('ApprovalMemos/Show', [
            'approval_memo' => new ApprovalMemoResource($approvalMemo),
            'activity_logs' => ActivityLogResource::collection(
                $this->activityLogRepository->getTimelineForApprovalMemo($approvalMemo)
            ),
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->values(),
            'modules' => collect(ApprovalVoucherModule::cases())->map(fn (ApprovalVoucherModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
            ])->values(),
            'actions' => collect(ApprovalMemoAction::cases())->map(fn (ApprovalMemoAction $action) => [
                'value' => $action->value,
                'label' => $action->label(),
            ])->values(),
        ]);
    }

    public function print(Request $request, int $approvalMemo): Response
    {
        return Inertia::render('ApprovalMemos/Print', [
            'approval_memo' => new ApprovalMemoResource(
                $this->approvalMemoRepository->findApprovedForViewerOrFail($request->user(), $approvalMemo)
            ),
            'auto_print' => $request->boolean('autoprint'),
        ]);
    }

    public function download(Request $request, int $approvalMemo): SymfonyResponse
    {
        $approvalMemo = $this->approvalMemoRepository->findApprovedForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        return Pdf::loadView('pdfs.approval-memo', [
            'approvalMemo' => $approvalMemo,
        ])
            ->setPaper('a4')
            ->download("{$approvalMemo->memo_no}.pdf");
    }

    public function store(
        UpsertApprovalMemoRequest $request,
        StoreApprovalMemoService $storeApprovalMemoService,
    ): RedirectResponse {
        $approvalMemo = $storeApprovalMemoService->handle($request->user(), $request->validated());

        return to_route('approval-memos.show', $approvalMemo)
            ->with(
                'success',
                $approvalMemo->status === ApprovalMemoStatus::PendingApproval
                    ? 'Approval memo submitted for approval.'
                    : 'Approval memo draft created.',
            );
    }

    public function update(
        UpsertApprovalMemoRequest $request,
        int $approvalMemo,
        UpdateApprovalMemoService $updateApprovalMemoService,
    ): RedirectResponse {
        $existingApprovalMemo = $this->approvalMemoRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        $updateApprovalMemoService->handle(
            $request->user(),
            $existingApprovalMemo,
            $request->validated(),
        );

        return back()->with('success', 'Approval memo updated.');
    }

    public function submit(
        SubmitApprovalMemoRequest $request,
        int $approvalMemo,
        SubmitApprovalMemoService $submitApprovalMemoService,
    ): RedirectResponse {
        $existingApprovalMemo = $this->approvalMemoRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        $submitApprovalMemoService->handle($request->user(), $existingApprovalMemo);

        return back()->with('success', 'Approval memo submitted for approval.');
    }

    public function approve(
        ApproveApprovalMemoRequest $request,
        int $approvalMemo,
        ApproveApprovalMemoService $approveApprovalMemoService,
    ): RedirectResponse {
        $existingApprovalMemo = $this->approvalMemoRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        $approveApprovalMemoService->handle(
            $request->user(),
            $existingApprovalMemo,
            $request->validated(),
        );

        return back()->with('success', 'Approval memo approved.');
    }

    public function reject(
        RejectApprovalMemoRequest $request,
        int $approvalMemo,
        RejectApprovalMemoService $rejectApprovalMemoService,
    ): RedirectResponse {
        $existingApprovalMemo = $this->approvalMemoRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        $rejectApprovalMemoService->handle(
            $request->user(),
            $existingApprovalMemo,
            $request->validated(),
        );

        return back()->with('success', 'Approval memo rejected.');
    }

    public function destroy(
        Request $request,
        int $approvalMemo,
        DeleteApprovalMemoService $deleteApprovalMemoService,
    ): RedirectResponse {
        $existingApprovalMemo = $this->approvalMemoRepository->findBasicForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        $deleteApprovalMemoService->handle($request->user(), $existingApprovalMemo);

        return to_route('approval-memos.index')->with('success', 'Approval memo deleted.');
    }
}
