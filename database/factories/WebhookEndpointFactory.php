<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
final class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => ucfirst(fake()->word()).' sync',
            'url' => 'https://hooks.'.fake()->domainWord().'.example/revora',
            'secret' => WebhookEndpoint::generateSecret(),
            'events' => [WebhookEvent::LeadCreated->value],
            'is_active' => true,
            'consecutive_failures' => 0,
        ];
    }

    /**
     * Subscribed to specific events.
     *
     * @param  list<WebhookEvent>  $events
     */
    public function subscribedTo(array $events): self
    {
        return $this->state(fn (): array => [
            'events' => array_map(fn (WebhookEvent $event): string => $event->value, $events),
        ]);
    }

    /** Retired automatically after a run of failures. */
    public function disabled(string $reason = 'Disabled automatically after repeated failures.'): self
    {
        return $this->state(fn (): array => [
            'disabled_at' => now(),
            'disabled_reason' => $reason,
            'consecutive_failures' => WebhookEndpoint::FAILURE_LIMIT,
        ]);
    }

    /** Switched off by hand, which is distinct from having been retired. */
    public function paused(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** Partway through a failure run, for testing the disable threshold. */
    public function failing(int $failures): self
    {
        return $this->state(fn (): array => ['consecutive_failures' => $failures]);
    }
}
