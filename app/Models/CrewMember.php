<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Someone on the crew who can be booked onto a schedule entry.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $email
 * @property string $status
 */
final class CrewMember extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];
}
