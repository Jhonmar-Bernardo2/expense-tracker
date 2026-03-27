<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\UserRepository;

class UpdatePasswordService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function handle(User $user, string $password): User
    {
        return $this->userRepository->updatePassword($user, $password);
    }
}
