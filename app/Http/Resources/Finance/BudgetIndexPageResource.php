<?php

namespace App\Http\Resources\Finance;

use App\Http\Resources\Shared\BudgetAllocationResource;
use App\Http\Resources\Shared\BudgetResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetIndexPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $financialManagementDepartment = $this['financial_management_department'];
        $month = (int) $this['month'];
        $year = (int) $this['year'];

        return [
            'budgets' => BudgetResource::collection($this['budgets'])->resolve($request),
            'active_allocation' => $this['active_allocation'] === null
                ? null
                : (new BudgetAllocationResource($this['active_allocation']))->resolve($request),
            'allocation_summary' => $this['allocation_summary'],
            'budget_presets' => $this['budget_presets']
                ->map(fn ($preset) => [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'items' => $preset->items
                        ->map(fn ($item) => [
                            'id' => $item->id,
                            'category_id' => $item->category_id,
                            'category_name' => $item->category?->name,
                            'amount_limit' => round((float) $item->amount_limit, 2),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'categories' => $this['categories']
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'budget_presets' => $category->budgetPresets
                        ->map(fn ($preset) => [
                            'id' => $preset->id,
                            'name' => $preset->name,
                            'amount_limit' => round((float) $preset->pivot->amount_limit, 2),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'departments' => [
                $financialManagementDepartment->toSummaryArray(),
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
                'department' => $financialManagementDepartment->id,
            ],
            'department_scope' => [
                'department_id' => $financialManagementDepartment->id,
                'selected_department' => $financialManagementDepartment->toSummaryArray(),
                'can_select_department' => false,
                'is_all_departments' => false,
            ],
            'financial_management_department' => $financialManagementDepartment->toSummaryArray(),
            'months' => collect(range(1, 12))->map(fn (int $monthOption) => [
                'value' => $monthOption,
                'label' => CarbonImmutable::create($year, $monthOption, 1)->format('F'),
            ])->values()->all(),
            'years' => collect($this['years'])
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
        ];
    }
}
