<?php

namespace App\Http\Controllers\App;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\ApproveApprovalVoucherRequest;
use App\Http\Requests\App\IndexApprovalVoucherRequest;
use App\Http\Requests\App\RejectApprovalVoucherRequest;
use App\Http\Requests\App\SubmitApprovalVoucherRequest;
use App\Http\Requests\App\UpsertApprovalVoucherRequest;
use App\Http\Resources\App\ApprovalVoucherIndexPageResource;
use App\Http\Resources\App\ApprovalVoucherPrintPageResource;
use App\Http\Resources\App\ApprovalVoucherShowPageResource;
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
use Barryvdh\DomPDF\Facade\Pdf;
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

        return Inertia::render('app/ApprovalVouchers/Index', (new ApprovalVoucherIndexPageResource([
            'approval_vouchers' => $this->approvalVoucherRepository->getForIndex(
                $request->user(),
                $scope['department_id'],
                $filters,
            ),
            'departments' => $this->getApprovalVoucherDepartmentOptions($request->user()),
            'department_scope' => $scope,
            'filters' => [
                'department' => $scope['department_id'],
                'status' => $filters['status']?->value,
                'module' => $filters['module']?->value,
                'action' => $filters['action']?->value,
                'search' => $filters['search'],
            ],
        ]))->resolve($request));
    }

    public function show(Request $request, int $approvalVoucher): Response
    {
        $approvalVoucher = $this->approvalVoucherRepository->findForViewerOrFail($request->user(), $approvalVoucher);

        return Inertia::render('app/ApprovalVouchers/Show', (new ApprovalVoucherShowPageResource([
            'approval_voucher' => $approvalVoucher,
            'activity_logs' => $this->activityLogRepository->getTimelineForApprovalVoucher($approvalVoucher),
            'categories' => $this->categoryRepository->getForIndex(),
            'departments' => $this->getApprovalVoucherDepartmentOptions($request->user()),
        ]))->resolve($request));
    }

    public function print(Request $request, int $approvalVoucher): Response
    {
        return Inertia::render(
            'app/ApprovalVouchers/Print',
            $this->buildPrintableVoucherPayload($request, $approvalVoucher),
        );
    }

    public function download(Request $request, int $approvalVoucher)
    {
        ['pdf' => $pdf, 'voucher_number' => $voucherNumber] = $this->buildVoucherPdf($request, $approvalVoucher);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$voucherNumber}.pdf\"",
        ]);
    }

    public function store(
        UpsertApprovalVoucherRequest $request,
        StoreApprovalVoucherService $storeApprovalVoucherService,
    ): RedirectResponse {
        $approvalVoucher = $storeApprovalVoucherService->handle($request->user(), $request->validated());

        return to_route('app.approval-vouchers.show', $approvalVoucher)
            ->with('success', $this->approvalVoucherStoreSuccessMessage($approvalVoucher));
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

        $submittedApprovalVoucher = $submitApprovalVoucherService->handle($request->user(), $existingApprovalVoucher);

        return back()->with(
            'success',
            $submittedApprovalVoucher->status === ApprovalVoucherStatus::Approved
                ? 'Transaction applied immediately.'
                : 'Request sent for review.',
        );
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

    /**
     * @return array{
     *     approval_voucher: array<string, mixed>,
     *     categories: array<int, array{id: int, name: string, type: string}>,
     *     departments: array<int, array{id: int, name: string, is_financial_management: bool, is_locked: bool}>
     * }
     */
    private function buildPrintableVoucherPayload(Request $request, int $approvalVoucher): array
    {
        $voucher = $this->approvalVoucherRepository->findForViewerOrFail($request->user(), $approvalVoucher);

        return (new ApprovalVoucherPrintPageResource([
            'approval_voucher' => $voucher,
            'categories' => $this->categoryRepository->getForIndex(),
            'departments' => $this->getApprovalVoucherDepartmentOptions($request->user()),
        ]))->resolve($request);
    }

    /**
     * @return array{pdf: \Barryvdh\DomPDF\PDF, voucher_number: string}
     */
    private function buildVoucherPdf(Request $request, int $approvalVoucher): array
    {
        $payload = $this->buildPrintableVoucherPayload($request, $approvalVoucher);
        $voucherNumber = (string) ($payload['approval_voucher']['voucher_no'] ?? 'approval-voucher');

        return [
            'pdf' => Pdf::loadView('pdfs.approval-voucher', $payload)
                ->setPaper('a4', 'portrait'),
            'voucher_number' => $voucherNumber,
        ];
    }

    private function approvalVoucherStoreSuccessMessage($approvalVoucher): string
    {
        if ($approvalVoucher->status === ApprovalVoucherStatus::Approved) {
            return $approvalVoucher->module === ApprovalVoucherModule::Transaction
                ? 'Transaction applied immediately.'
                : 'Request approved and applied.';
        }

        return $approvalVoucher->status === ApprovalVoucherStatus::PendingApproval
            ? 'Request sent for review.'
            : 'Draft request created.';
    }
}
