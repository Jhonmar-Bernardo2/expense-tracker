<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationRepository
{
    public function getForIndex(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForUserOrFail(User $user, string $notificationId): DatabaseNotification
    {
        return $user->notifications()->findOrFail($notificationId);
    }

    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $this->findForUserOrFail($user, $notificationId);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification->refresh();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
