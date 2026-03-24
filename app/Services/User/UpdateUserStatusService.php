<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;

class UpdateUserStatusService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function handle(User $user, bool $isActive): User
    {
        if ($user->isAdmin() && ! $isActive && $this->userRepository->countActiveAdmins() <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'At least one active admin account is required.',
            ]);
        }

        return $this->userRepository->updateStatus($user, $isActive);
    }
}
