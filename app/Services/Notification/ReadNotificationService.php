<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Notifications\DatabaseNotification;

class ReadNotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function handle(User $user, string $notificationId): DatabaseNotification
    {
        return $this->notificationRepository->markAsRead($user, $notificationId);
    }
}
