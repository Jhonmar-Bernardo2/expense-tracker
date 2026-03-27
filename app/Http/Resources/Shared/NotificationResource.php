<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            'id' => $this->id,
            'title' => (string) ($data['title'] ?? 'Notification'),
            'body' => (string) ($data['body'] ?? ''),
            'href' => isset($data['href']) && is_string($data['href']) && $data['href'] !== ''
                ? $data['href']
                : null,
            'meta' => isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [],
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
