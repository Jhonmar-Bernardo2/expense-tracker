<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\UserIndexPageResource;
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
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Users/Index', (new UserIndexPageResource([
            'users' => $this->userRepository->getForIndex(),
            'departments' => $this->departmentRepository->getOptions(),
        ]))->resolve(request()));
    }

    public function store(
        UpsertUserRequest $request,
        StoreUserService $storeUserService,
    ): RedirectResponse {
        $storeUserService->handle($request->validated());

        return back()->with('success', 'User added.');
    }

    public function update(
        UpsertUserRequest $request,
        int $user,
        UpdateUserService $updateUserService,
    ): RedirectResponse {
        $existingUser = $this->userRepository->findOrFail($user);

        $updateUserService->handle($existingUser, $request->validated());

        return back()->with('success', 'User updated.');
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
