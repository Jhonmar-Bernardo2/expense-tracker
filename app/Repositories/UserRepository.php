<?php

namespace App\Repositories;

use App\Enums\ApprovalVoucherModule;
use App\Enums\UserRole;
use App\Models\ApprovalVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * @return Collection<int, User>
     */
    public function getForIndex(): Collection
    {
        return User::query()
            ->with('department:id,name')
            ->orderBy('name')
            ->orderBy('email')
            ->get();
    }

    public function findOrFail(int $userId): User
    {
        return User::query()
            ->with('department:id,name')
            ->findOrFail($userId);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', strtolower(trim($email)))
            ->first();
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string, department_id: int}  $data
     */
    public function create(array $data): User
    {
        return User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'role' => $data['role'],
            'department_id' => $data['department_id'],
            'is_active' => true,
            'email_verified_at' => now(),
        ])->load('department:id,name');
    }

    /**
     * @param  array{name: string, email: string, role: string, department_id: int, is_active: bool}  $data
     */
    public function update(User $user, array $data): User
    {
        $email = strtolower(trim($data['email']));

        $user->update([
            'name' => trim($data['name']),
            'email' => $email,
            'role' => $data['role'],
            'department_id' => $data['department_id'],
            'is_active' => $data['is_active'],
            'email_verified_at' => $user->email === $email
                ? $user->email_verified_at
                : now(),
        ]);

        return $user->refresh()->load('department:id,name');
    }

    public function updateStatus(User $user, bool $isActive): User
    {
        $user->update([
            'is_active' => $isActive,
        ]);

        return $user->refresh()->load('department:id,name');
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $email = strtolower(trim($data['email']));

        $user->fill([
            'name' => trim($data['name']),
            'email' => $email,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user->refresh();
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->update([
            'password' => $password,
        ]);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function countActiveAdmins(): int
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function getActiveApproversForSubmission(ApprovalVoucher $approvalVoucher): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->when(
                in_array($approvalVoucher->module, [ApprovalVoucherModule::Allocation, ApprovalVoucherModule::Budget], true),
                fn ($query) => $query->where('role', UserRole::Admin->value),
                fn ($query) => $query
                    ->where('role', '!=', UserRole::Admin->value)
                    ->whereHas(
                        'department',
                        fn ($departmentQuery) => $departmentQuery->where('is_financial_management', true),
                    ),
            )
            ->get();
    }
}
