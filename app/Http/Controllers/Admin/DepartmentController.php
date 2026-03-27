<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertDepartmentRequest;
use App\Http\Resources\Admin\DepartmentIndexPageResource;
use App\Repositories\DepartmentRepository;
use App\Services\Department\DeleteDepartmentService;
use App\Services\Department\StoreDepartmentService;
use App\Services\Department\UpdateDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentRepository $departmentRepository,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/Departments/Index', (new DepartmentIndexPageResource([
            'departments' => $this->departmentRepository->getForIndex(),
        ]))->resolve($request));
    }

    public function store(
        UpsertDepartmentRequest $request,
        StoreDepartmentService $storeDepartmentService,
    ): RedirectResponse {
        $storeDepartmentService->handle($request->validated());

        return back()->with('success', 'Department added.');
    }

    public function update(
        UpsertDepartmentRequest $request,
        int $department,
        UpdateDepartmentService $updateDepartmentService,
    ): RedirectResponse {
        $existingDepartment = $this->departmentRepository->findOrFail($department);

        if ($existingDepartment->isLocked()) {
            return back()->with('error', 'The Finance Team department is protected and cannot be changed.');
        }

        $updateDepartmentService->handle($existingDepartment, $request->validated());

        return back()->with('success', 'Department updated.');
    }

    public function destroy(
        Request $request,
        int $department,
        DeleteDepartmentService $deleteDepartmentService,
    ): RedirectResponse {
        $existingDepartment = $this->departmentRepository->findOrFail($department);
        $error = $deleteDepartmentService->handle($existingDepartment);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Department deleted.');
    }
}
