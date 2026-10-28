<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Models\User;
use App\Models\UserNotification;

/**
 * Writes a notice for the people who are allowed to see it.
 *
 * The person who caused the change is left out: they already know. Anyone
 * without the permission is left out too, so the text never reaches them.
 */
final class NotifyPermittedUsers
{
    public function handle(
        string $permission,
        string $title,
        string $body,
        ?string $href = null,
        ?int $exceptUserId = null,
    ): void {
        $except = $exceptUserId ?? auth()->id();

        User::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($permission, $title, $body, $href, $except): void {
                if ($except !== null && $user->id === (int) $except) {
                    return;
                }

                if (! $user->can($permission)) {
                    return;
                }

                $this->toUser($user, $title, $body, $href);
            });
    }

    public function toUser(User $user, string $title, string $body, ?string $href = null): void
    {
        if ($user->id === auth()->id()) {
            return;
        }

        UserNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'href' => $href,
        ]);
    }
}
