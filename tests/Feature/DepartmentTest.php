<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_management_department_is_bootstrapped_and_locked(): void
    {
        $this->assertSame(1, Department::query()->where('is_financial_management', true)->count());

        $this->assertDatabaseHas('departments', [
            'name' => 'Financial Management',
            'description' => 'Central budget department.',
            'is_financial_management' => true,
            'is_locked' => true,
        ]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $department = Department::factory()->create();

        $this->get(route('departments.index'))
            ->assertRedirect(route('login'));

        $this->post(route('departments.store'), [
            'name' => 'Finance',
            'description' => 'Handles spending controls.',
        ])->assertRedirect(route('login'));

        $this->put(route('departments.update', $department), [
            'name' => 'Finance',
            'description' => 'Updated',
        ])->assertRedirect(route('login'));

        $this->delete(route('departments.destroy', $department))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_access_department_management_routes(): void
    {
        $department = Department::factory()->create();
        $staff = User::factory()->create([
            'department_id' => $department->id,
        ]);

        $otherDepartment = Department::factory()->create();

        $this->actingAs($staff)
            ->get(route('departments.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('departments.store'), [
                'name' => 'Operations',
                'description' => null,
            ])->assertForbidden();

        $this->actingAs($staff)
            ->put(route('departments.update', $otherDepartment), [
                'name' => 'Operations',
                'description' => null,
            ])->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('departments.destroy', $otherDepartment))
            ->assertForbidden();
    }

    public function test_admin_can_view_department_index_with_locked_financial_management_department(): void
    {
        $department = Department::factory()->create([
            'name' => 'Finance',
            'description' => 'Controls expenses.',
        ]);

        $admin = User::factory()->admin()->create([
            'department_id' => $department->id,
        ]);

        User::factory()->create([
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)
            ->get(route('departments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Departments/Index')
                ->has('departments', 3)
                ->where('departments.0.name', 'Finance')
                ->where('departments.0.description', 'Controls expenses.')
                ->where('departments.0.user_count', 2)
                ->where('departments.0.can_delete', false)
                ->where('departments.1.name', 'Financial Management')
                ->where('departments.1.is_financial_management', true)
                ->where('departments.1.is_locked', true)
                ->where('departments.1.can_delete', false)
                ->where('departments.2.name', 'General')
            );
    }

    public function test_admin_can_create_a_department_and_name_is_normalized(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => '  Finance   Team  ',
                'description' => '  Handles   approvals  ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'name' => 'Finance Team',
            'description' => 'Handles approvals',
        ]);
    }

    public function test_admin_cannot_create_a_duplicate_department_name(): void
    {
        $admin = $this->createAdmin();
        Department::factory()->create([
            'name' => 'Finance',
        ]);

        $this->actingAs($admin)
            ->from(route('departments.index'))
            ->post(route('departments.store'), [
                'name' => 'Finance',
                'description' => null,
            ])
            ->assertRedirect(route('departments.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_a_non_locked_department(): void
    {
        $department = Department::factory()->create([
            'name' => 'Operations',
            'description' => 'Ops',
        ]);
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->put(route('departments.update', $department), [
                'name' => '  Operations Team ',
                'description' => ' Daily coordination ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Operations Team',
            'description' => 'Daily coordination',
        ]);
    }

    public function test_admin_cannot_update_or_delete_the_locked_financial_management_department(): void
    {
        $financialManagementDepartment = $this->financialManagementDepartment();
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->from(route('departments.index'))
            ->put(route('departments.update', $financialManagementDepartment), [
                'name' => 'Renamed Financial Management',
                'description' => 'Updated',
            ])
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('error', 'The Finance Team department is protected and cannot be changed.');

        $this->actingAs($admin)
            ->from(route('departments.index'))
            ->delete(route('departments.destroy', $financialManagementDepartment))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('error', 'The Finance Team department is protected and cannot be deleted.');

        $this->assertDatabaseHas('departments', [
            'id' => $financialManagementDepartment->id,
            'name' => 'Financial Management',
            'is_financial_management' => true,
            'is_locked' => true,
        ]);
    }

    public function test_admin_cannot_delete_department_that_has_assigned_users_or_financial_records(): void
    {
        $department = Department::factory()->create([
            'name' => 'Finance',
        ]);
        $admin = User::factory()->admin()->create([
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)
            ->from(route('departments.index'))
            ->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('error', 'This department cannot be deleted because it still has assigned users or financial records.');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_admin_can_delete_an_unused_department(): void
    {
        $department = Department::factory()->create();
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->delete(route('departments.destroy', $department))
            ->assertRedirect();

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    private function createAdmin(): User
    {
        $department = Department::factory()->create();

        return User::factory()->admin()->create([
            'department_id' => $department->id,
        ]);
    }

    private function financialManagementDepartment(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }
}
