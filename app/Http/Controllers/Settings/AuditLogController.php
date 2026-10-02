<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reads the audit trail (§54).
 *
 * Read-only by design: there is no edit and no delete, because a trail somebody
 * can tidy up is not evidence of anything.
 */
final class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'action' => ['sometimes', 'array'],
            'action.*' => [Rule::in(AuditAction::values())],
            'actor' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $logs = AuditLog::query()
            // AuditLog is central with a nullable tenant_id so platform and
            // tenant actions share one trail, which means the scope has to be
            // applied here by hand rather than by the global scope.
            ->where('tenant_id', $this->context->tenantOrFail()->id)
            ->with('actor')
            ->when(
                $request->filled('action'),
                fn (Builder $q) => $q->whereIn('action', (array) $request->input('action')),
            )
            ->when(
                $request->filled('actor'),
                fn (Builder $q) => $q
                    ->where('actor_type', User::class)
                    ->where('actor_id', $request->integer('actor')),
            )
            ->when(
                $request->filled('from'),
                fn (Builder $q) => $q->where('created_at', '>=', $request->date('from')),
            )
            ->when(
                $request->filled('to'),
                // End of the chosen day, not its first instant: a filter that
                // excludes everything that happened on the day you asked for is
                // a filter nobody trusts twice.
                fn (Builder $q) => $q->where('created_at', '<=', $request->date('to')?->endOfDay()),
            )
            // Newest first: an audit trail is read from the incident backwards.
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('settings/AuditLog', [
            'logs' => [
                'data' => collect($logs->items())
                    ->map(fn (AuditLog $log): array => $this->present($log))
                    ->all(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ],
            'actions' => AuditAction::options(),
            'actors' => User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
                ->all(),
            'filters' => $request->only(['action', 'actor', 'from', 'to']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AuditLog $log): array
    {
        $action = AuditAction::tryFrom($log->action);

        return [
            'id' => $log->id,
            'action' => $log->action,
            // Falls back to the raw value rather than hiding a row whose action
            // predates a rename: an unreadable label is better than a gap.
            'action_label' => $action?->label() ?? $log->action,
            'is_destructive' => $action?->isDestructive() ?? false,
            'actor' => $this->actor($log),
            'entity' => $this->entity($log),
            'before' => $log->before,
            'after' => $log->after,
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{type: string, name: string}
     */
    private function actor(AuditLog $log): array
    {
        $actor = $log->actor;

        if ($actor instanceof User) {
            return ['type' => 'user', 'name' => $actor->name];
        }

        if ($actor instanceof ApiKey) {
            // Named as a key, not as whoever created it: "the Zapier key did
            // this" and "Amara did this" are different facts.
            return ['type' => 'api_key', 'name' => $actor->name.' (API key)'];
        }

        // Nothing to name: a queue job, a scheduled task or a console command.
        return ['type' => 'system', 'name' => 'System'];
    }

    /**
     * @return array{type: string, id: int|string|null}|null
     */
    private function entity(AuditLog $log): ?array
    {
        if ($log->entity_type === null) {
            return null;
        }

        return [
            'type' => class_basename($log->entity_type),
            'id' => $log->entity_id,
        ];
    }
}
