<?php

namespace App\Http\Resources\Admin;

use App\Enums\UserRole;
use App\Http\Resources\Shared\ManagedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserIndexPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'users' => ManagedUserResource::collection($this['users'])->resolve($request),
            'departments' => $this['departments']
                ->map(fn ($department) => $department->toSummaryArray())
                ->values()
                ->all(),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values()->all(),
        ];
    }
}
