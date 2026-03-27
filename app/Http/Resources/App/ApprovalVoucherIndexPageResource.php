<?php

namespace App\Http\Resources\App;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Http\Resources\Concerns\ResolvesPaginatedResources;
use App\Http\Resources\Shared\ApprovalVoucherResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalVoucherIndexPageResource extends JsonResource
{
    use ResolvesPaginatedResources;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'approval_vouchers' => $this->paginatedResource(
                $request,
                $this['approval_vouchers'],
                ApprovalVoucherResource::class,
            ),
            'departments' => $this['departments']
                ->map(fn ($department) => $department->toSummaryArray())
                ->values()
                ->all(),
            'department_scope' => $this['department_scope'],
            'filters' => $this['filters'],
            'statuses' => collect(ApprovalVoucherStatus::cases())->map(fn (ApprovalVoucherStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
            'modules' => collect(ApprovalVoucherModule::cases())->map(fn (ApprovalVoucherModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
            ])->values()->all(),
            'actions' => collect(ApprovalVoucherAction::cases())->map(fn (ApprovalVoucherAction $action) => [
                'value' => $action->value,
                'label' => $action->label(),
            ])->values()->all(),
        ];
    }
}
