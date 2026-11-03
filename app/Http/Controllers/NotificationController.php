<?php

namespace App\Http\Controllers;

use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The notification bell: open a notification or mark them all as read.
 * Notifications belong to a user in one company workspace.
 */
class NotificationController extends Controller
{
    /**
     * Open a notification: mark it as read and go to the page it is about.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()
            ->notifications()
            ->where('data->team_id', app(CurrentTeam::class)->id())
            ->findOrFail($notification);

        $item->markAsRead();

        $url = (string) ($item->data['url'] ?? '');

        // Only pages of this app, never another site.
        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : to_route('dashboard');
    }

    /**
     * Mark all notifications of the current workspace as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->where('data->team_id', app(CurrentTeam::class)->id())
            ->update(['read_at' => now()]);

        return back();
    }
}
