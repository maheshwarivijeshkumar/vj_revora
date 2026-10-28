<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A visit or block of work on the schedule.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $client_id
 * @property int|null $project_id
 * @property int|null $crew_member_id
 * @property string $title
 * @property CarbonInterface $starts_at
 * @property CarbonInterface|null $ends_at
 * @property string|null $location
 * @property string|null $notes
 */
final class ScheduleEntry extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
