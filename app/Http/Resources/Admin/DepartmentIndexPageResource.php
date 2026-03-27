<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Shared\DepartmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentIndexPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'departments' => DepartmentResource::collection($this['departments'])->resolve($request),
        ];
    }
}
