<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Marks one notice read. Opening the list does not do this: the notice stays
 * unread until the person it belongs to opens it.
 */
final class NotificationController extends Controller
{
    public function update(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()?->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }
}
