<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'summary' => $this->summary,
            'meta' => $this->meta ?? [],
            'actor' => $this->whenLoaded('actor', fn () => $this->actor === null
                ? null
                : [
                    'id' => $this->actor->id,
                    'name' => $this->actor->name,
                    'email' => $this->actor->email,
                ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
