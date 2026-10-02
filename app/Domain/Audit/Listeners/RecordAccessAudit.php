<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Records who signed in, out, and who tried and failed (§54).
 *
 * Failed attempts are included deliberately: a run of them against one address
 * is the signal that matters, and a trail that only shows successes cannot show
 * it.
 */
final class RecordAccessAudit
{
    public function __construct(
        private readonly AuditRecorder $recorder,
        private readonly TenantContext $context,
    ) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->forUser($user, fn () => $this->recorder->record(AuditAction::Login, actor: $user));
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        if ($user === null) {
            return;
        }

        $this->forUser($user, fn () => $this->recorder->record(AuditAction::Logout, actor: $user));
    }

    public function handleFailed(Failed $event): void
    {
        // The address that was tried, never the password it was tried with. An
        // audit trail must not become a place credentials are stored (§55).
        $attempted = $event->credentials['email'] ?? null;
        $user = $event->user instanceof User ? $event->user : null;

        $this->forUser($user, fn () => $this->recorder->record(
            AuditAction::LoginFailed,
            after: ['email' => is_string($attempted) ? $attempted : null],
            actor: $user,
        ));
    }

    /**
     * Binds the user's workspace while the row is written.
     *
     * Sign-in happens before tenant resolution, so without this every login
     * would be recorded against no workspace and be invisible to the people it
     * concerns. A failed attempt on an unknown address has no workspace to bind,
     * and is recorded centrally rather than guessed at.
     *
     * @param  Closure(): mixed  $callback
     */
    private function forUser(?User $user, Closure $callback): void
    {
        $tenant = $user === null
            ? null
            : $this->context->withoutScoping(
                fn (): ?Tenant => Tenant::query()->find($user->tenant_id),
            );

        if (! $tenant instanceof Tenant) {
            $callback();

            return;
        }

        $this->context->runAs($tenant, $callback);
    }
}
