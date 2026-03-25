<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalVoucher;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ApprovalVoucherAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Concerns\CreatesApprovalMemos;

class ApprovalVoucherAuditNotificationsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApprovalMemos;

    public function test_auto_submitting_a_voucher_notifies_active_admins_and_logs_submission(): void
    {
        [$department, $staff, $admin, $category] = $this->makeTransactionContext();
        $secondAdmin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $approvalMemo = $this->createApprovedMemo($staff, $department, [
            'module' => 'transaction',
            'action' => 'create',
        ]);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $department->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Team lunch',
                'amount' => 450,
                'description' => 'Monthly team lunch',
                'transaction_date' => '2026-03-24',
                'approval_memo_id' => $approvalMemo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->assertSame('pending_approval', $approvalVoucher->status->value);
        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame(1, $secondAdmin->fresh()->notifications()->count());
        $this->assertSame(0, $inactiveAdmin->fresh()->notifications()->count());

        $notificationData = $admin->fresh()->notifications()->firstOrFail()->data;

        $this->assertSame('Approval request submitted', $notificationData['title'] ?? null);
        $this->assertSame($approvalVoucher->id, $notificationData['meta']['approval_voucher_id'] ?? null);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $approvalVoucher->getMorphClass(),
            'subject_id' => $approvalVoucher->id,
            'actor_id' => $staff->id,
            'department_id' => $department->id,
            'event' => 'approval_voucher.submitted',
        ]);
    }

    public function test_approving_a_voucher_notifies_the_requester_and_logs_the_applied_change(): void
    {
        [$department, $staff, $admin, $category] = $this->makeTransactionContext();
        $approvalVoucher = $this->submitTransactionVoucher($staff, $department, $category, [
            'title' => 'Software subscription',
            'amount' => 1200,
        ]);

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Approved for payment.',
            ])
            ->assertRedirect();

        $approvalVoucher->refresh();
        $transaction = Transaction::query()->firstOrFail();
        $requesterNotification = $staff->fresh()->notifications()->latest('id')->firstOrFail();

        $this->assertSame('approved', $approvalVoucher->status->value);
        $this->assertSame($approvalVoucher->id, $transaction->origin_approval_voucher_id);
        $this->assertSame('Approval request approved', $requesterNotification->data['title'] ?? null);
        $this->assertSame($approvalVoucher->id, $requesterNotification->data['meta']['approval_voucher_id'] ?? null);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $approvalVoucher->getMorphClass(),
            'subject_id' => $approvalVoucher->id,
            'actor_id' => $admin->id,
            'event' => 'approval_voucher.approved',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $approvalVoucher->getMorphClass(),
            'subject_id' => $approvalVoucher->id,
            'actor_id' => $admin->id,
            'event' => 'transaction.applied_from_voucher',
        ]);
    }

    public function test_rejecting_a_voucher_notifies_the_requester_and_logs_rejection_metadata(): void
    {
        [$department, $staff, $admin, $category] = $this->makeTransactionContext();
        $approvalVoucher = $this->submitTransactionVoucher($staff, $department, $category);

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.reject', $approvalVoucher), [
                'rejection_reason' => 'Missing supporting receipt.',
            ])
            ->assertRedirect();

        $approvalVoucher->refresh();
        $requesterNotification = $staff->fresh()->notifications()->latest('id')->firstOrFail();
        $rejectionLog = ActivityLog::query()
            ->where('subject_type', $approvalVoucher->getMorphClass())
            ->where('subject_id', $approvalVoucher->id)
            ->where('event', 'approval_voucher.rejected')
            ->firstOrFail();

        $this->assertSame('rejected', $approvalVoucher->status->value);
        $this->assertSame('Approval request rejected', $requesterNotification->data['title'] ?? null);
        $this->assertSame(
            'Missing supporting receipt.',
            $requesterNotification->data['meta']['rejection_reason'] ?? null,
        );
        $this->assertSame('Missing supporting receipt.', $rejectionLog->meta['rejection_reason'] ?? null);
    }

    public function test_notification_inbox_is_scoped_to_the_authenticated_user_and_read_actions_do_not_touch_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->notify(new ApprovalVoucherAlertNotification(
            'Mine',
            'Visible in my inbox.',
            '/approval-vouchers/1',
        ));
        $user->notify(new ApprovalVoucherAlertNotification(
            'Mark all mine',
            'Also visible in my inbox.',
            '/approval-vouchers/2',
        ));
        $otherUser->notify(new ApprovalVoucherAlertNotification(
            'Theirs',
            'Must stay private.',
            '/approval-vouchers/3',
        ));

        $myNotifications = $user->fresh()->notifications;
        $myNotification = $myNotifications->first(
            fn ($notification) => ($notification->data['title'] ?? null) === 'Mine',
        );
        $markAllNotification = $myNotifications->first(
            fn ($notification) => ($notification->data['title'] ?? null) === 'Mark all mine',
        );
        $otherNotification = $otherUser->fresh()->notifications()->firstOrFail();

        $this->assertNotNull($myNotification);
        $this->assertNotNull($markAllNotification);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notification_items.data', 2)
                ->where('notification_items.data', fn ($items) => collect($items)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all() === collect([
                        $myNotification->id,
                        $markAllNotification->id,
                    ])->sort()->values()->all())
            );

        $this->actingAs($user)
            ->patch(route('notifications.read', $otherNotification->id))
            ->assertNotFound();

        $this->assertNull($otherNotification->fresh()->read_at);

        $this->actingAs($user)
            ->patch(route('notifications.read', $myNotification->id))
            ->assertRedirect();

        $this->assertNotNull($myNotification->fresh()->read_at);

        $this->actingAs($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_voucher_detail_timeline_is_only_returned_to_authorized_viewers(): void
    {
        [$department, $staff, $admin, $category] = $this->makeTransactionContext();
        $approvalVoucher = $this->submitTransactionVoucher($staff, $department, $category);
        $otherStaff = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('approval-vouchers.show', $approvalVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApprovalVouchers/Show')
                ->where('approval_voucher.id', $approvalVoucher->id)
                ->has('activity_logs', 2)
                ->where('activity_logs.0.event', 'approval_voucher.submitted')
            );

        $this->actingAs($otherStaff)
            ->get(route('approval-vouchers.show', $approvalVoucher))
            ->assertNotFound();
    }

    public function test_pending_vouchers_older_than_three_days_are_marked_as_overdue(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');

        try {
            $department = Department::factory()->create(['name' => 'Finance']);
            $staff = User::factory()->create(['department_id' => $department->id]);
            $category = Category::query()->create([
                'name' => 'Office supplies',
                'type' => 'expense',
            ]);

            $approvalVoucher = ApprovalVoucher::query()->create([
                'voucher_no' => 'AV-2026-00001',
                'department_id' => $department->id,
                'requested_by' => $staff->id,
                'module' => 'transaction',
                'action' => 'create',
                'status' => 'pending_approval',
                'target_id' => null,
                'before_payload' => null,
                'after_payload' => [
                    'department_id' => $department->id,
                    'category_id' => $category->id,
                    'type' => 'expense',
                    'title' => 'Late request',
                    'amount' => 150,
                    'description' => 'Still waiting',
                    'transaction_date' => '2026-03-20',
                ],
                'remarks' => 'Please review.',
                'submitted_at' => '2026-03-20 09:30:00',
                'created_at' => '2026-03-20 09:00:00',
                'updated_at' => '2026-03-20 09:30:00',
            ]);

            $this->actingAs($staff)
                ->get(route('approval-vouchers.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('approval_vouchers.data.0.id', $approvalVoucher->id)
                    ->where('approval_vouchers.data.0.pending_age_days', 4)
                    ->where('approval_vouchers.data.0.is_overdue', true)
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{0: Department, 1: User, 2: User, 3: Category}
     */
    private function makeTransactionContext(): array
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Software',
            'type' => 'expense',
        ]);

        return [$department, $staff, $admin, $category];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitTransactionVoucher(
        User $staff,
        Department $department,
        Category $category,
        array $overrides = [],
    ): ApprovalVoucher {
        $payload = array_merge([
            'module' => 'transaction',
            'action' => 'create',
            'department_id' => $department->id,
            'type' => 'expense',
            'category_id' => $category->id,
            'title' => 'Team lunch',
            'amount' => 450,
            'description' => 'Monthly team lunch',
            'transaction_date' => '2026-03-24',
        ], $overrides);

        $payload['approval_memo_id'] ??= $this->createApprovedMemo($staff, $department, [
            'module' => 'transaction',
            'action' => $payload['action'] ?? 'create',
        ])->id;
        $payload['approval_memo_pdf'] ??= $this->makeApprovalMemoPdfUpload();
        $payload['auto_submit'] ??= true;

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), $payload)
            ->assertRedirect();

        return ApprovalVoucher::query()->latest('id')->firstOrFail();
    }
}
