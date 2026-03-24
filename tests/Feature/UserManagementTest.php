<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->get(route('users.index'))
            ->assertRedirect(route('login'));

        $this->post(route('users.store'), [
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'role' => UserRole::Staff->value,
            'department_id' => $user->department_id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $this->put(route('users.update', $user), [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'role' => UserRole::Staff->value,
            'department_id' => $user->department_id,
            'is_active' => true,
        ])->assertRedirect(route('login'));

        $this->patch(route('users.status.update', $user), [
            'is_active' => false,
        ])->assertRedirect(route('login'));
    }

    public function test_staff_cannot_access_user_management_routes(): void
    {
        $department = Department::factory()->create();
        $staff = User::factory()->create([
            'department_id' => $department->id,
        ]);
        $managedUser = User::factory()->create([
            'department_id' => $department->id,
        ]);

        $this->actingAs($staff)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('users.store'), [
                'name' => 'New Staff',
                'email' => 'newstaff@example.com',
                'role' => UserRole::Staff->value,
                'department_id' => $department->id,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertForbidden();

        $this->actingAs($staff)
            ->put(route('users.update', $managedUser), [
                'name' => 'Updated Staff',
                'email' => 'updatedstaff@example.com',
                'role' => UserRole::Staff->value,
                'department_id' => $department->id,
                'is_active' => true,
            ])->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('users.status.update', $managedUser), [
                'is_active' => false,
            ])->assertForbidden();
    }

    public function test_admin_can_view_user_management_index_with_expected_props(): void
    {
        $department = Department::factory()->create([
            'name' => 'Finance',
        ]);
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'department_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users', 2)
                ->where('users.0.name', 'Admin User')
                ->where('users.0.role', UserRole::Admin->value)
                ->where('users.1.name', 'Staff User')
                ->where('users.1.role', UserRole::Staff->value)
                ->has('departments', 2)
                ->has('roles', 2)
            );
    }

    public function test_admin_can_create_user_with_role_department_and_immediate_access_flags(): void
    {
        $admin = $this->createAdmin();
        $department = Department::factory()->create([
            'name' => 'Operations',
        ]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => '  Operations Staff  ',
                'email' => 'OPERATIONS@example.com',
                'role' => UserRole::Staff->value,
                'department_id' => $department->id,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect();

        $createdUser = User::query()->where('email', 'operations@example.com')->first();

        $this->assertNotNull($createdUser);
        $this->assertSame(UserRole::Staff, $createdUser->role);
        $this->assertSame($department->id, $createdUser->department_id);
        $this->assertTrue($createdUser->is_active);
        $this->assertNotNull($createdUser->email_verified_at);
    }

    public function test_admin_can_update_user_role_department_email_and_status(): void
    {
        $primaryDepartment = Department::factory()->create([
            'name' => 'Finance',
        ]);
        $secondaryDepartment = Department::factory()->create([
            'name' => 'Operations',
        ]);
        $admin = User::factory()->admin()->create([
            'department_id' => $primaryDepartment->id,
        ]);
        $managedUser = User::factory()->create([
            'email' => 'staff@example.com',
            'department_id' => $primaryDepartment->id,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('users.update', $managedUser), [
                'name' => 'Updated Staff',
                'email' => 'updated-staff@example.com',
                'role' => UserRole::Admin->value,
                'department_id' => $secondaryDepartment->id,
                'is_active' => false,
            ])
            ->assertRedirect();

        $managedUser->refresh();

        $this->assertSame('Updated Staff', $managedUser->name);
        $this->assertSame('updated-staff@example.com', $managedUser->email);
        $this->assertSame(UserRole::Admin, $managedUser->role);
        $this->assertSame($secondaryDepartment->id, $managedUser->department_id);
        $this->assertFalse($managedUser->is_active);
        $this->assertNotNull($managedUser->email_verified_at);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $admin = $this->createAdmin();
        $managedUser = User::factory()->create([
            'department_id' => $admin->department_id,
        ]);

        $this->actingAs($admin)
            ->patch(route('users.status.update', $managedUser), [
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'is_active' => false,
        ]);
    }

    public function test_last_active_admin_cannot_be_demoted_via_update(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => UserRole::Staff->value,
                'department_id' => $admin->department_id,
                'is_active' => true,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_last_active_admin_cannot_be_deactivated_via_status_route(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->patch(route('users.status.update', $admin), [
                'is_active' => false,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_protected_system_accounts_cannot_be_updated_or_deactivated(): void
    {
        $admin = $this->createAdmin();
        $systemAccount = User::factory()->systemAccount()->create([
            'department_id' => $admin->department_id,
        ]);

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->put(route('users.update', $systemAccount), [
                'name' => 'Changed Name',
                'email' => $systemAccount->email,
                'role' => UserRole::Staff->value,
                'department_id' => $admin->department_id,
                'is_active' => true,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('user');

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->patch(route('users.status.update', $systemAccount), [
                'is_active' => false,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('is_active');

        $systemAccount->refresh();

        $this->assertTrue($systemAccount->is_system_account);
        $this->assertTrue($systemAccount->is_active);
        $this->assertSame(UserRole::Admin, $systemAccount->role);
    }

    public function test_inactive_users_cannot_continue_using_protected_pages(): void
    {
        $inactiveUser = User::factory()->inactive()->create();

        $response = $this->actingAs($inactiveUser)
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Your account is inactive. Please contact an administrator.');
        $this->assertGuest();
    }

    public function test_active_admin_and_staff_can_still_access_business_pages(): void
    {
        $department = Department::factory()->create();
        $admin = User::factory()->admin()->create([
            'department_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('categories.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('categories.index'))
            ->assertOk();
    }

    private function createAdmin(): User
    {
        $department = Department::factory()->create();

        return User::factory()->admin()->create([
            'department_id' => $department->id,
        ]);
    }
}
