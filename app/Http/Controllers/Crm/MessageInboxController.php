<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email and WhatsApp already stored against leads, newest first.
 */
final class MessageInboxController extends Controller
{
    public function __invoke(): Response
    {
        $messages = Message::query()
            ->with('lead')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Message $message): array => [
                'id' => $message->id,
                'channel' => $message->channel->value,
                'direction' => $message->direction->value,
                'subject' => $message->subject,
                'body' => $message->body === null ? null : mb_strimwidth($message->body, 0, 180, '…'),
                'status' => $message->status->value,
                'lead_id' => $message->lead_id,
                'lead' => $message->lead?->displayName(),
                'at' => ($message->sent_at ?? $message->received_at ?? $message->created_at)?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('crm/messages/Index', [
            'messages' => $messages,
        ]);
    }
}
