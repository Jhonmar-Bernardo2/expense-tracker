<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Http\Requests\ApproveVoucherLiquidationRequest;
use App\Http\Requests\ApproveVoucherRequest;
use App\Http\Requests\IndexVoucherRequest;
use App\Http\Requests\RejectVoucherRequest;
use App\Http\Requests\ReleaseVoucherRequest;
use App\Http\Requests\ReturnVoucherLiquidationRequest;
use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\SubmitLiquidationRequest;
use App\Http\Requests\SubmitVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Repositories\CategoryRepository;
use App\Repositories\VoucherRepository;
use App\Services\Department\DepartmentScopeService;
use App\Services\Voucher\ApproveVoucherLiquidationService;
use App\Services\Voucher\ApproveVoucherService;
use App\Services\Voucher\RejectVoucherService;
use App\Services\Voucher\ReleaseVoucherService;
use App\Services\Voucher\ReturnVoucherLiquidationService;
use App\Services\Voucher\StoreVoucherService;
use App\Services\Voucher\SubmitLiquidationService;
use App\Services\Voucher\SubmitVoucherService;
use App\Services\Voucher\UpdateVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {
    }

    public function index(IndexVoucherRequest $request): Response
    {
        $validated = $request->validated();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        $filters = [
            'status' => isset($validated['status'])
                ? VoucherStatus::from($validated['status'])
                : null,
            'type' => isset($validated['type'])
                ? VoucherType::from($validated['type'])
                : null,
            'search' => $validated['search'] ?? null,
        ];

        return Inertia::render('Vouchers/Index', [
            'vouchers' => VoucherResource::collection(
                $this->voucherRepository->getForIndex($request->user(), $scope['department_id'], $filters)
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
                'type' => $filters['type']?->value,
                'search' => $filters['search'],
            ],
            'statuses' => collect(VoucherStatus::cases())->map(fn (VoucherStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'types' => collect(VoucherType::cases())->map(fn (VoucherType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
        ]);
    }

    public function show(Request $request, int $voucher): Response
    {
        return Inertia::render('Vouchers/Show', [
            'voucher' => new VoucherResource(
                $this->voucherRepository->findForViewerOrFail($request->user(), $voucher)
            ),
            'expense_categories' => $this->categoryRepository->getExpenseOptions()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values(),
        ]);
    }

    public function store(
        StoreVoucherRequest $request,
        StoreVoucherService $storeVoucherService,
    ): RedirectResponse {
        $validated = $request->validated();
        $departmentId = $this->departmentScopeService->resolveWritableDepartmentId(
            $request->user(),
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );

        $storeVoucherService->handle($request->user(), $departmentId, $validated);

        return back()->with('success', 'Voucher request created.');
    }

    public function update(
        UpdateVoucherRequest $request,
        int $voucher,
        UpdateVoucherService $updateVoucherService,
    ): RedirectResponse {
        $validated = $request->validated();
        $departmentId = $this->departmentScopeService->resolveWritableDepartmentId(
            $request->user(),
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $updateVoucherService->handle($request->user(), $existingVoucher, $departmentId, $validated);

        return back()->with('success', 'Voucher request updated.');
    }

    public function submit(
        SubmitVoucherRequest $request,
        int $voucher,
        SubmitVoucherService $submitVoucherService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $submitVoucherService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Voucher submitted for approval.');
    }

    public function approve(
        ApproveVoucherRequest $request,
        int $voucher,
        ApproveVoucherService $approveVoucherService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $approveVoucherService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Voucher approved.');
    }

    public function reject(
        RejectVoucherRequest $request,
        int $voucher,
        RejectVoucherService $rejectVoucherService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $rejectVoucherService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Voucher rejected.');
    }

    public function release(
        ReleaseVoucherRequest $request,
        int $voucher,
        ReleaseVoucherService $releaseVoucherService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $releaseVoucherService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Voucher released.');
    }

    public function submitLiquidation(
        SubmitLiquidationRequest $request,
        int $voucher,
        SubmitLiquidationService $submitLiquidationService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $submitLiquidationService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Liquidation submitted.');
    }

    public function returnLiquidation(
        ReturnVoucherLiquidationRequest $request,
        int $voucher,
        ReturnVoucherLiquidationService $returnVoucherLiquidationService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $returnVoucherLiquidationService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Liquidation returned for correction.');
    }

    public function approveLiquidation(
        ApproveVoucherLiquidationRequest $request,
        int $voucher,
        ApproveVoucherLiquidationService $approveVoucherLiquidationService,
    ): RedirectResponse {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);

        $approveVoucherLiquidationService->handle($request->user(), $existingVoucher, $request->validated());

        return back()->with('success', 'Liquidation approved and posted to transactions.');
    }

    public function downloadAttachment(Request $request, int $voucher, int $attachment): StreamedResponse
    {
        $existingVoucher = $this->voucherRepository->findBasicForViewerOrFail($request->user(), $voucher);
        $existingAttachment = $this->voucherRepository->findAttachmentForVoucherOrFail($existingVoucher, $attachment);

        return Storage::disk($existingAttachment->disk)->download(
            $existingAttachment->path,
            $existingAttachment->original_name,
        );
    }
}
