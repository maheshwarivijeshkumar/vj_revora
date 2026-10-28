<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An outside firm hired onto a job.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $trade
 * @property string|null $company
 * @property string|null $phone
 * @property string|null $email
 * @property string $status
 */
final class Subcontractor extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];
}
