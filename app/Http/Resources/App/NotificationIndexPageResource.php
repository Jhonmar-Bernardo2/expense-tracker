<?php

namespace App\Http\Resources\App;

use App\Http\Resources\Concerns\ResolvesPaginatedResources;
use App\Http\Resources\Shared\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationIndexPageResource extends JsonResource
{
    use ResolvesPaginatedResources;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'notification_items' => $this->paginatedResource(
                $request,
                $this['notification_items'],
                NotificationResource::class,
            ),
        ];
    }
}
