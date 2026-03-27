<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\ManagedUserResource;
use App\Repositories\DepartmentRepository;
use App\Repositories\UserRepository;
use App\Services\User\StoreUserService;
use App\Services\User\UpdateUserService;
use App\Services\User\UpdateUserStatusService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DepartmentRepository $departmentRepository,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => ManagedUserResource::collection(
                $this->userRepository->getForIndex()
            ),
            'departments' => $this->departmentRepository
                ->getOptions()
                ->map(fn ($department) => $department->toSummaryArray())
                ->values(),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values(),
        ]);
    }

    public function store(
        StoreUserRequest $request,
        StoreUserService $storeUserService,
    ): RedirectResponse {
        $storeUserService->handle($request->validated());

        return back()->with('success', 'User account created.');
    }

    public function update(
        UpdateUserRequest $request,
        int $user,
        UpdateUserService $updateUserService,
    ): RedirectResponse {
        $existingUser = $this->userRepository->findOrFail($user);

        $updateUserService->handle($existingUser, $request->validated());

        return back()->with('success', 'User account updated.');
    }

    public function updateStatus(
        UpdateUserStatusRequest $request,
        int $user,
        UpdateUserStatusService $updateUserStatusService,
    ): RedirectResponse {
        $existingUser = $this->userRepository->findOrFail($user);

        $updateUserStatusService->handle($existingUser, $request->boolean('is_active'));

        return back()->with('success', 'User status updated.');
    }
}
