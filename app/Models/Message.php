<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One email or WhatsApp message to or from a lead (§15, §25).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int|null $lead_id
 * @property MessageChannel $channel
 * @property MessageDirection $direction
 * @property int|null $email_account_id
 * @property int|null $follow_up_enrollment_id
 * @property int|null $step_position
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $from_address
 * @property string|null $to_address
 * @property string|null $provider_message_id
 * @property string|null $in_reply_to
 * @property MessageStatus $status
 * @property string|null $error
 * @property int|null $user_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 */
final class Message extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $message): void {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
