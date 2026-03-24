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

    public function test_admin_can_view_department_index_with_expected_props(): void
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
                ->has('departments', 2)
                ->where('departments.0.name', 'Finance')
                ->where('departments.0.description', 'Controls expenses.')
                ->where('departments.0.user_count', 2)
                ->where('departments.0.can_delete', false)
                ->where('departments.1.name', 'General')
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

    public function test_admin_can_update_a_department(): void
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
}
