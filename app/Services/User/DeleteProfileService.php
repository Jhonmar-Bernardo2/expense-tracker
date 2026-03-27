<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\UserRepository;

class DeleteProfileService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function handle(User $user): void
    {
        $this->userRepository->delete($user);
    }
}
