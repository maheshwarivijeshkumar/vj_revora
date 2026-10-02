<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail (§54).
 *
 * Synchronous and inside whatever transaction the audited action runs in, so a
 * recorded change and the record of it cannot disagree. An audit row is one
 * insert; queueing it would buy throughput at the cost of the only property
 * that makes the trail worth keeping.
 */
final class AuditRecorder
{
    /**
     * Attribute names whose values must never reach the trail.
     *
     * Matched as substrings, because the trail is written from whatever a model
     * happens to be carrying and an exact-name list would miss the next secret
     * somebody adds (§55).
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'secret',
        'token',
        'hash',
        'remember_token',
        'two_factor',
        'api_key',
        'signature',
        'credential',
    ];

    private const REDACTION = '[redacted]';

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AuditAction $action,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?Model $actor = null,
    ): AuditLog {
        $request = $this->request();
        $actor ??= $this->actor();

        // forceCreate because the audit trail is written on behalf of the
        // workspace being audited, which the mass-assignment guard on
        // tenant_id deliberately refuses to take from input.
        return AuditLog::forceCreate([
            'tenant_id' => $this->context->id(),
            'actor_type' => $actor === null ? null : $actor::class,
            'actor_id' => $actor?->getKey(),
            'action' => $action->value,
            'entity_type' => $entity === null ? null : $entity::class,
            'entity_id' => $entity?->getKey(),
            'before' => $this->scrub($before),
            'after' => $this->scrub($after),
            'ip' => $request?->ip(),
            'user_agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    /**
     * Records a model's change, keeping only what actually differs.
     *
     * The whole row would make the trail unreadable: the question is "what did
     * this person change", and an unchanged column is noise that hides it.
     */
    public function recordChange(AuditAction $action, Model $model): AuditLog
    {
        $changed = $model->getChanges();

        // Timestamps move on every save and say nothing about intent.
        unset($changed['updated_at'], $changed['created_at']);

        $before = [];

        foreach (array_keys($changed) as $key) {
            $before[$key] = $model->getOriginal($key);
        }

        return $this->record($action, $model, $before === [] ? null : $before, $changed === [] ? null : $changed);
    }

    /**
     * Who is doing this.
     *
     * An API key is an actor in its own right rather than the person who
     * created it: "the Zapier key deleted this" and "Amara deleted this" are
     * different facts, and conflating them makes the trail lie.
     */
    private function actor(): ?Model
    {
        $request = $this->request();

        if ($request !== null) {
            $key = $request->attributes->get('api_key');

            if ($key instanceof ApiKey) {
                return $key;
            }
        }

        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * The request, when there is one.
     *
     * There is not during a queue job, a scheduled task or a console command,
     * and a trail that invented an IP for those would be worse than one that
     * leaves them blank.
     */
    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app(Request::class);

        // A console or queue run still resolves a Request object, but an empty
        // one. The absence of a client address is what distinguishes it from a
        // real HTTP request, and inventing an IP for those would make the trail
        // lie about where an action came from.
        return $request->ip() === null ? null : $request;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function scrub(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $scrubbed = [];

        foreach ($values as $key => $value) {
            $scrubbed[$key] = $this->isSensitive((string) $key)
                ? self::REDACTION
                : $this->normalise($value);
        }

        return $scrubbed;
    }

    private function isSensitive(string $key): bool
    {
        $lower = mb_strtolower($key);

        foreach (self::REDACTED as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduces a value to something JSON can hold and a human can read.
     */
    private function normalise(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }

        if (is_array($value)) {
            return $this->scrub($value);
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }
}
