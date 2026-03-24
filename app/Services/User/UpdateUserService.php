<?php

namespace App\Services\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;

class UpdateUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @param  array{name: string, email: string, role: string, department_id: int, is_active: bool}  $data
     */
    public function handle(User $user, array $data): User
    {
        if ($user->isSystemAccount()) {
            throw ValidationException::withMessages([
                'user' => 'System accounts are protected and cannot be modified here.',
            ]);
        }

        $willLoseAdminAccess = $user->isAdmin()
            && ($data['role'] !== UserRole::Admin->value || ! $data['is_active']);

        if ($willLoseAdminAccess && $this->userRepository->countActiveAdmins() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'At least one active admin account is required.',
            ]);
        }

        return $this->userRepository->update($user, $data);
    }
}
