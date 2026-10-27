<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email in a sequence, sent `delay_minutes` after the lead was enrolled.
 *
 * @property int $id
 * @property int $follow_up_sequence_id
 * @property int $position
 * @property int $delay_minutes
 * @property int|null $email_template_id
 */
final class FollowUpStep extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<EmailTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }
}
