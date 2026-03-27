<?php

namespace App\Http\Resources\App;

use App\Http\Resources\Shared\BudgetAllocationResource;
use App\Http\Resources\Shared\BudgetResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportsPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'departments' => $this['departments']
                ->map(fn ($department) => $department->toSummaryArray())
                ->values()
                ->all(),
            'filters' => $this['filters'],
            'department_scope' => $this['department_scope'],
            'summary' => [
                'monthly' => $this['monthly_summary'],
                'yearly' => $this['yearly_summary'],
            ],
            'breakdowns' => [
                'expenses_by_category' => $this['expenses_by_category'],
                'budget_vs_actual' => ! $this['can_view_budget_summaries']
                    ? []
                    : BudgetResource::collection($this['budget_vs_actual'])->resolve($request),
            ],
            'charts' => [
                'income_vs_expenses' => $this['income_vs_expenses'],
                'spending_trend' => $this['spending_trend'],
            ],
            'options' => [
                'months' => $this['months']->values()->all(),
                'years' => collect($this['years'])
                    ->unique()
                    ->sortDesc()
                    ->values()
                    ->all(),
            ],
            'budget_summary' => ! $this['can_view_budget_summaries'] ? null : [
                'scope_label' => 'Central monthly budget',
                'financial_management_department' => $this['financial_management_department']->toSummaryArray(),
                'active_allocation' => $this['active_allocation'] === null
                    ? null
                    : (new BudgetAllocationResource($this['active_allocation']))->resolve($request),
                'current_month_summary' => $this['budget_period_summary'],
            ],
        ];
    }
}
