<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\UserRepository;

class StoreUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string, department_id: int}  $data
     */
    public function handle(array $data): User
    {
        return $this->userRepository->create($data);
    }
}
