<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\App\NotificationIndexPageResource;
use App\Repositories\NotificationRepository;
use App\Services\Notification\ReadAllNotificationsService;
use App\Services\Notification\ReadNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('app/Notifications/Index', (new NotificationIndexPageResource([
            'notification_items' => $this->notificationRepository->getForIndex($request->user()),
        ]))->resolve($request));
    }

    public function read(
        Request $request,
        string $notification,
        ReadNotificationService $readNotificationService,
    ): RedirectResponse
    {
        $readNotificationService->handle($request->user(), $notification);

        return back();
    }

    public function readAll(
        Request $request,
        ReadAllNotificationsService $readAllNotificationsService,
    ): RedirectResponse
    {
        $readAllNotificationsService->handle($request->user());

        return back();
    }
}
