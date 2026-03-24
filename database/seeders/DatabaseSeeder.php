<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $department = Department::query()->firstOrCreate(
            ['name' => env('SYSTEM_ADMIN_DEPARTMENT', 'General')],
            ['description' => 'System administrator department.'],
        );

        User::query()->updateOrCreate(
            ['email' => env('SYSTEM_ADMIN_EMAIL', 'superadmin@gmail.com')],
            [
                'name' => env('SYSTEM_ADMIN_NAME', 'Super Admin'),
                'password' => env('SYSTEM_ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin->value,
                'department_id' => $department->id,
                'is_active' => true,
                'is_system_account' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
