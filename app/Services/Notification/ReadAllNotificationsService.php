<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Repositories\NotificationRepository;

class ReadAllNotificationsService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function handle(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);
    }
}
