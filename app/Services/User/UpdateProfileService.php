<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\UserRepository;

class UpdateProfileService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        return $this->userRepository->updateProfile($user, $data);
    }
}
