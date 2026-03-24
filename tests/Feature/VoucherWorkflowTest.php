<?php

namespace Tests\Feature;

use App\Enums\VoucherStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VoucherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_run_the_full_voucher_flow_and_transactions_are_only_posted_after_liquidation_approval(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($staff)
            ->post(route('vouchers.store'), [
                'department_id' => $otherDepartment->id,
                'type' => 'cash_advance',
                'purpose' => 'Team meal',
                'requested_amount' => 1500,
                'remarks' => 'Quarterly planning lunch',
            ])
            ->assertRedirect();

        $voucher = Voucher::query()->firstOrFail();

        $this->assertSame($department->id, $voucher->department_id);
        $this->assertSame(VoucherStatus::Draft, $voucher->status);
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($staff)
            ->post(route('vouchers.submit', $voucher), [])
            ->assertRedirect();

        $this->assertSame(VoucherStatus::PendingApproval, $voucher->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('vouchers.approve', $voucher), [
                'approved_amount' => 1400,
                'liquidation_due_date' => '2026-03-31',
                'remarks' => 'Approved with reduced amount',
            ])
            ->assertRedirect();

        $this->assertSame(VoucherStatus::Approved, $voucher->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('vouchers.release', $voucher), [
                'released_amount' => 1400,
                'remarks' => 'Funds released',
            ])
            ->assertRedirect();

        $this->assertSame(VoucherStatus::Released, $voucher->fresh()->status);

        $this->actingAs($staff)
            ->post(route('vouchers.liquidation.submit', $voucher), [
                'remarks' => 'Receipts attached',
                'items' => [
                    [
                        'category_id' => $category->id,
                        'description' => 'Lunch buffet',
                        'amount' => 900,
                        'expense_date' => '2026-03-24',
                    ],
                    [
                        'category_id' => $category->id,
                        'description' => 'Drinks',
                        'amount' => 300,
                        'expense_date' => '2026-03-24',
                    ],
                ],
                'attachments' => [
                    UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $voucher->refresh();

        $this->assertSame(VoucherStatus::LiquidationSubmitted, $voucher->status);
        $this->assertCount(2, $voucher->items);
        $this->assertCount(1, $voucher->attachments);
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($admin)
            ->patch(route('vouchers.liquidation.approve', $voucher), [
                'remarks' => 'Liquidation approved',
            ])
            ->assertRedirect();

        $voucher->refresh();

        $this->assertSame(VoucherStatus::LiquidationApproved, $voucher->status);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'voucher_id' => $voucher->id,
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'title' => 'Lunch buffet',
            'type' => 'expense',
        ]);
        $this->assertDatabaseHas('transactions', [
            'voucher_id' => $voucher->id,
            'title' => 'Drinks',
        ]);
    }

    public function test_staff_only_sees_vouchers_from_their_department(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $otherStaff = User::factory()->create(['department_id' => $otherDepartment->id]);

        $visibleVoucher = Voucher::query()->create([
            'voucher_no' => 'VCH-2026-00001',
            'department_id' => $department->id,
            'requested_by' => $staff->id,
            'type' => 'cash_advance',
            'status' => VoucherStatus::Draft->value,
            'purpose' => 'Finance supplies',
            'requested_amount' => 500,
        ]);

        Voucher::query()->create([
            'voucher_no' => 'VCH-2026-00002',
            'department_id' => $otherDepartment->id,
            'requested_by' => $otherStaff->id,
            'type' => 'cash_advance',
            'status' => VoucherStatus::Draft->value,
            'purpose' => 'Operations supplies',
            'requested_amount' => 800,
        ]);

        $this->actingAs($staff)
            ->get(route('vouchers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vouchers/Index')
                ->where('filters.department', $department->id)
                ->where('department_scope.department_id', $department->id)
                ->where('vouchers.data.0.id', $visibleVoucher->id)
                ->missing('vouchers.data.1')
            );
    }

    public function test_transactions_keep_a_link_back_to_the_posted_voucher(): void
    {
        $department = Department::factory()->create();
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Transportation',
            'type' => 'expense',
        ]);
        $voucher = Voucher::query()->create([
            'voucher_no' => 'VCH-2026-00003',
            'department_id' => $department->id,
            'requested_by' => $staff->id,
            'type' => 'cash_advance',
            'status' => VoucherStatus::LiquidationApproved->value,
            'purpose' => 'Field visit',
            'requested_amount' => 1000,
        ]);

        Transaction::query()->create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'voucher_id' => $voucher->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Taxi',
            'amount' => 250,
            'description' => null,
            'transaction_date' => '2026-03-24',
        ]);

        $this->assertDatabaseHas('transactions', [
            'voucher_id' => $voucher->id,
            'title' => 'Taxi',
        ]);
    }
}
