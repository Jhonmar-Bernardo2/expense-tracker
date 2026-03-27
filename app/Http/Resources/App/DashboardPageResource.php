<?php

namespace App\Http\Resources\App;

use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Http\Resources\Shared\ApprovalVoucherResource;
use App\Http\Resources\Shared\BudgetAllocationResource;
use App\Http\Resources\Shared\BudgetResource;
use App\Http\Resources\Shared\TransactionResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class DashboardPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $budgetPayload = $this->buildBudgetPayload($request);

        return [
            'departments' => $this['departments']
                ->map(fn ($department) => $department->toSummaryArray())
                ->values()
                ->all(),
            'department_scope' => $this['department_scope'],
            'totals' => $this['totals'],
            'current_month' => $this['current_month'],
            'budgets' => $budgetPayload,
            'recent_transactions' => TransactionResource::collection($this['recent_transactions'])->resolve($request),
            'charts' => $this['charts'],
            'dashboard_view' => $this->buildDashboardView($request, $budgetPayload),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBudgetPayload(Request $request): ?array
    {
        if (! $this['can_view_budget_summaries']) {
            return null;
        }

        return [
            'scope_label' => 'Central monthly budget',
            'financial_management_department' => $this['financial_management_department']->toSummaryArray(),
            'active_allocation' => $this['active_allocation'] === null
                ? null
                : (new BudgetAllocationResource($this['active_allocation']))->resolve($request),
            'current_month_summary' => $this['budget_period_summary'],
            'current_month_statuses' => BudgetResource::collection($this['current_month_statuses'])->resolve($request),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $budgetPayload
     * @return array<string, mixed>
     */
    private function buildDashboardView(Request $request, ?array $budgetPayload): array
    {
        $mode = $this['mode'];
        $totals = $this['totals'];
        $currentMonth = $this['current_month'];

        return match ($mode) {
            'admin' => [
                'mode' => $mode,
                'title' => 'Organization overview',
                'description' => 'Monitor cash flow, monthly budget requests, and overall spending.',
                'primary_metrics' => [
                    $this->metric('total-income', 'Total income', $totals['income'], 'currency', 'Across the selected scope.', 'success'),
                    $this->metric('total-expenses', 'Total expenses', $totals['expenses'], 'currency', 'Across the selected scope.', 'warning'),
                    $this->metric('net-balance', 'Net balance', $totals['balance'], 'currency', 'Income minus expenses.', $totals['balance'] < 0 ? 'danger' : 'info'),
                    $this->metric(
                        'pending-allocation-approvals',
                        'Pending budget requests',
                        $this['admin_pending_allocation_count'],
                        'number',
                        'Monthly budget requests waiting for review.',
                        'info',
                    ),
                ],
                'quick_actions' => [
                    $this->action(
                        'review-allocation-approvals',
                        'Review budget requests',
                        route('app.approval-vouchers.index', [
                            'module' => ApprovalVoucherModule::Allocation->value,
                            'status' => ApprovalVoucherStatus::PendingApproval->value,
                        ], false),
                        'default',
                        'file-text',
                    ),
                    $this->action('open-reports', 'View reports', route('app.reports.index', [], false), 'outline', 'bar-chart-3'),
                    $this->action('review-central-budget', 'View monthly budget', route('finance.budgets.index', [], false), 'secondary', 'piggy-bank'),
                ],
                'primary_section' => $this->section(
                    'allocation-approvals',
                    'Latest budget requests',
                    'Monthly budget requests, with items waiting for review first.',
                    'No monthly budget requests are visible right now.',
                    ApprovalVoucherResource::collection($this['admin_allocation_items'])->resolve($request),
                ),
                'attention_banner' => null,
                'secondary_section' => null,
            ],
            'financial_management' => [
                'mode' => $mode,
                'title' => 'Finance Team overview',
                'description' => 'Review requests, manage category budgets, and track the monthly budget.',
                'primary_metrics' => [
                    $this->metric(
                        'approved-allocation',
                        'Approved monthly budget',
                        $budgetPayload['current_month_summary']['approved_allocation'] ?? 0,
                        'currency',
                        'Current approved monthly budget.',
                        'info',
                    ),
                    $this->metric(
                        'allocated-categories',
                        'Budget set for categories',
                        $budgetPayload['current_month_summary']['total_allocated'] ?? 0,
                        'currency',
                        'Amount already set aside for categories.',
                        'info',
                    ),
                    $this->metric(
                        'unallocated',
                        'Budget left to assign',
                        $budgetPayload['current_month_summary']['total_unallocated'] ?? 0,
                        'currency',
                        'Still available for category assignment.',
                        'warning',
                    ),
                    $this->metric(
                        'spent',
                        'Total spent',
                        $budgetPayload['current_month_summary']['total_spent'] ?? 0,
                        'currency',
                        'Approved expense transactions this month.',
                        'warning',
                    ),
                    $this->metric(
                        'remaining',
                        'Budget left after spending',
                        $budgetPayload['current_month_summary']['total_remaining'] ?? 0,
                        'currency',
                        'Approved monthly budget minus spending.',
                        ($budgetPayload['current_month_summary']['total_remaining'] ?? 0) < 0 ? 'danger' : 'success',
                    ),
                    $this->metric(
                        'pending-transaction-approvals',
                        'Requests waiting for review',
                        $this['finance_pending_transaction_count'],
                        'number',
                        'Department requests waiting for Finance Team review.',
                        'info',
                    ),
                ],
                'quick_actions' => [
                    $this->action(
                        'review-transaction-requests',
                        'Review requests',
                        route('app.approval-vouchers.index', [
                            'module' => ApprovalVoucherModule::Transaction->value,
                            'status' => ApprovalVoucherStatus::PendingApproval->value,
                        ], false),
                        'default',
                        'file-text',
                    ),
                    $this->action('manage-category-budgets', 'Manage budgets', route('finance.budgets.index', [], false), 'secondary', 'piggy-bank'),
                    $this->action('open-reports', 'View reports', route('app.reports.index', [], false), 'outline', 'bar-chart-3'),
                ],
                'primary_section' => $this->section(
                    'transaction-approval-queue',
                    'Requests waiting for review',
                    'Pending department requests that need Finance Team action.',
                    'No requests are waiting for review.',
                    ApprovalVoucherResource::collection($this['finance_pending_items'])->resolve($request),
                ),
                'attention_banner' => null,
                'secondary_section' => $this->section(
                    'recent-department-requests',
                    'Recent requests',
                    'Latest requests across departments, including recently processed items.',
                    'No recent requests are visible right now.',
                    ApprovalVoucherResource::collection($this['finance_recent_items'])->resolve($request),
                ),
            ],
            default => $this->buildStaffDashboardView($request, $currentMonth),
        };
    }

    /**
     * @param  array{month: int, year: int, income: float, expenses: float, balance: float}  $currentMonth
     * @return array<string, mixed>
     */
    private function buildStaffDashboardView(Request $request, array $currentMonth): array
    {
        $requesterCounts = $this['requester_counts'];
        /** @var Collection<int, mixed> $recentRequests */
        $recentRequests = $this['staff_recent_requests'];

        return [
            'mode' => 'staff',
            'title' => 'My dashboard',
            'description' => 'Track department activity, check request updates, and send new requests.',
            'primary_metrics' => [
                $this->metric(
                    'my-pending-requests',
                    'My open requests',
                    $requesterCounts['pending'],
                    'number',
                    'Requests still waiting for a final decision.',
                    'info',
                ),
                $this->metric(
                    'my-approved-requests',
                    'Approved this month',
                    $requesterCounts['approved_this_month'],
                    'number',
                    'Requests approved this month.',
                    'success',
                ),
                $this->metric(
                    'my-rejected-requests',
                    'Rejected this month',
                    $requesterCounts['rejected_this_month'],
                    'number',
                    'Requests rejected this month.',
                    'warning',
                ),
                $this->metric(
                    'department-expenses',
                    'Department spending this month',
                    $currentMonth['expenses'],
                    'currency',
                    'Approved expense transactions for your department.',
                    'warning',
                ),
            ],
            'quick_actions' => [
                $this->action(
                    'request-transaction',
                    'New request',
                    route('app.transactions.index', [], false),
                    'default',
                    'receipt',
                ),
            ],
            'attention_banner' => $this->buildStaffAttentionBanner($recentRequests, $requesterCounts),
            'primary_section' => $this->section(
                'my-request-statuses',
                'My requests',
                'Your latest requests, with the newest first.',
                'You have not sent any requests yet.',
                ApprovalVoucherResource::collection($recentRequests)->resolve($request),
                route('app.approval-vouchers.index', [], false),
                'My requests',
            ),
            'secondary_section' => null,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $recentRequests
     * @param  array{pending: int, approved_this_month: int, rejected_this_month: int}  $requesterCounts
     * @return array{tone: string, title: string, description: string, href: string|null, action_label: string|null}|null
     */
    private function buildStaffAttentionBanner(Collection $recentRequests, array $requesterCounts): ?array
    {
        $recentRejectedRequest = $recentRequests->first(
            fn ($voucher) => $voucher->status === ApprovalVoucherStatus::Rejected,
        );

        if ($recentRejectedRequest !== null) {
            $subject = str($recentRejectedRequest->resolveSubject())
                ->limit(72)
                ->toString();

            return [
                'tone' => 'warning',
                'title' => 'A recent request needs updates',
                'description' => "\"{$subject}\" was rejected recently. Review the feedback and resubmit when ready.",
                'href' => route('app.approval-vouchers.show', $recentRejectedRequest, false),
                'action_label' => 'Review request',
            ];
        }

        if ($requesterCounts['pending'] > 0) {
            $requestLabel = $requesterCounts['pending'] === 1 ? 'request is' : 'requests are';

            return [
                'tone' => 'info',
                'title' => 'You have requests awaiting review',
                'description' => "{$requesterCounts['pending']} {$requestLabel} still pending approval. Check the latest status updates any time.",
                'href' => route('app.approval-vouchers.index', [], false),
                'action_label' => 'My requests',
            ];
        }

        if ($recentRequests->isEmpty()) {
            return [
                'tone' => 'default',
                'title' => 'Start your first request',
                'description' => 'Submit a transaction request to begin tracking approvals and department spending from your dashboard.',
                'href' => route('app.transactions.index', [], false),
                'action_label' => 'New request',
            ];
        }

        return null;
    }

    /**
     * @return array{id: string, label: string, value: float|int|string, format: string, helper: string|null, tone: string}
     */
    private function metric(
        string $id,
        string $label,
        float|int|string $value,
        string $format,
        ?string $helper = null,
        string $tone = 'default',
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'helper' => $helper,
            'tone' => $tone,
        ];
    }

    /**
     * @return array{id: string, label: string, href: string, variant: string, icon: string|null}
     */
    private function action(
        string $id,
        string $label,
        string $href,
        string $variant = 'outline',
        ?string $icon = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'href' => $href,
            'variant' => $variant,
            'icon' => $icon,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{id: string, title: string, description: string, empty_message: string, items: array<int, mixed>, cta_href: string|null, cta_label: string|null}
     */
    private function section(
        string $id,
        string $title,
        string $description,
        string $emptyMessage,
        array $items,
        ?string $ctaHref = null,
        ?string $ctaLabel = null,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'empty_message' => $emptyMessage,
            'items' => $items,
            'cta_href' => $ctaHref,
            'cta_label' => $ctaLabel,
        ];
    }
}
