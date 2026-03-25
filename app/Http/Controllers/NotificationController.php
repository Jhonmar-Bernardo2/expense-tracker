<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Repositories\NotificationRepository;
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
        return Inertia::render('Notifications/Index', [
            'notification_items' => NotificationResource::collection(
                $this->notificationRepository->getForIndex($request->user())
            ),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->notificationRepository->markAsRead($request->user(), $notification);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notificationRepository->markAllAsRead($request->user());

        return back();
    }
}
