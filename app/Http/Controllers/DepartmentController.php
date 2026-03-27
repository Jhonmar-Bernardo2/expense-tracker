<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Repositories\DepartmentRepository;
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

    public function index(): Response
    {
        return Inertia::render('Departments/Index', [
            'departments' => DepartmentResource::collection(
                $this->departmentRepository->getForIndex()
            ),
        ]);
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

    public function destroy(Request $request, int $department): RedirectResponse
    {
        $existingDepartment = $this->departmentRepository->findOrFail($department);

        if ($existingDepartment->isLocked()) {
            return back()->with('error', 'The Finance Team department is protected and cannot be deleted.');
        }

        if ($this->departmentRepository->hasUsers($existingDepartment)) {
            return back()->with('error', 'This department cannot be deleted because it still has assigned users or financial records.');
        }

        $this->departmentRepository->delete($existingDepartment);

        return back()->with('success', 'Department deleted.');
    }
}
