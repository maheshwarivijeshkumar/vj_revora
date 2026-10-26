<?php

declare(strict_types=1);

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Messaging\Actions\SendWhatsAppToLead;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Messaging\Exceptions\CannotMessageLead;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\SendWhatsAppWelcome;
use App\Models\FollowUpEnrollment;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| WhatsApp Cloud API
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    config([
        'verification.queue_recheck' => false,
        'services.ai.reply_provider' => 'keywords',
        'services.whatsapp.api_version' => 'v26.0',
        'services.whatsapp.app_secret' => 'app-secret',
        'services.whatsapp.verify_token' => 'verify-me',
    ]);
    Http::preventStrayRequests();

    $this->account = new WhatsAppAccount;
    $this->account->forceFill([
        'tenant_id' => $this->tenant->id,
        'phone_number_id' => '1234567890',
        'display_phone_number' => '+92 300 0000000',
        'access_token' => 'wa-token',
        'welcome_template' => 'lead_welcome',
        'template_language' => 'en',
        'auto_send_welcome' => true,
        'status' => ConnectionStatus::Connected,
    ])->save();

    $this->source = LeadSource::factory()->create(['tenant_id' => $this->tenant->id]);
});

function waLead(array $payload): Lead
{
    return app(CaptureLead::class)->handle(app(LeadNormalizer::class)->normalize($payload), test()->source)->lead;
}

function signedWebhook(array $payload): array
{
    $body = json_encode($payload);

    return [$body, ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', (string) $body, 'app-secret'), 'Content-Type' => 'application/json']];
}

function inboundPayload(string $from, string $text, string $id = 'wamid.IN1'): array
{
    return ['entry' => [['changes' => [['value' => [
        'metadata' => ['phone_number_id' => '1234567890'],
        'messages' => [['from' => $from, 'id' => $id, 'timestamp' => (string) now()->getTimestamp(), 'type' => 'text', 'text' => ['body' => $text]]],
    ]]]]]];
}

it('queues the welcome template for a new lead who consented', function (): void {
    Queue::fake([SendWhatsAppWelcome::class]);

    waLead(['first_name' => 'Ali', 'phone' => '+923001234567', 'consent' => 'yes']);
    waLead(['first_name' => 'Sara', 'phone' => '+923007654321']);

    Queue::assertPushed(SendWhatsAppWelcome::class, 1);
});

it('sends the approved template with the first name filled in', function (): void {
    Http::fake(['graph.facebook.com/v26.0/1234567890/messages' => Http::response(['messages' => [['id' => 'wamid.OUT1']]])]);
    $lead = waLead(['first_name' => 'Ali', 'phone' => '+923001234567']);

    $message = app(SendWhatsAppToLead::class)->template($lead);

    expect($message->provider_message_id)->toBe('wamid.OUT1');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer wa-token')
        && $request['to'] === '923001234567'
        && $request['template']['name'] === 'lead_welcome'
        && $request['template']['components'][0]['parameters'][0]['text'] === 'Ali');
});

it('refuses free text outside the 24-hour window', function (): void {
    $lead = waLead(['phone' => '+923001234567']);

    expect(fn () => app(SendWhatsAppToLead::class)->text($lead, 'Hello'))->toThrow(CannotMessageLead::class);
    Http::assertNothingSent();
});

it('answers Meta\'s subscription check only with the right verify token', function (): void {
    $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=abc123')
        ->assertOk()->assertSee('abc123');

    $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
        ->assertForbidden();
});

it('rejects webhook calls that are not signed by Meta', function (): void {
    [$body] = signedWebhook(inboundPayload('923001234567', 'Hi'));

    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=forged'], $body)
        ->assertUnauthorized();

    expect(Message::query()->count())->toBe(0);
});

it('files a lead\'s WhatsApp reply, stops follow-up emails and scores it', function (): void {
    $lead = waLead(['first_name' => 'Ali', 'phone' => '+923001234567', 'email' => 'ali@client.example']);
    $sequence = FollowUpSequence::create(['name' => 'Seq', 'is_active' => true, 'no_reply_after_minutes' => 4320]);
    FollowUpEnrollment::create([
        'lead_id' => $lead->id, 'follow_up_sequence_id' => $sequence->id,
        'status' => EnrollmentStatus::Active, 'started_at' => now(), 'next_run_at' => now()->addHour(),
    ]);

    [$body, $headers] = signedWebhook(inboundPayload('923001234567', 'Please call me tomorrow'));
    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256']], $body)
        ->assertOk();

    // Meta retries: the same message again changes nothing.
    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256']], $body);

    expect(Message::query()->where('lead_id', $lead->id)->where('direction', 'inbound')->count())->toBe(1)
        ->and(FollowUpEnrollment::query()->sole()->status)->toBe(EnrollmentStatus::Replied)
        ->and($lead->fresh()->engagement_score)->toBe(9)
        ->and(app(SendWhatsAppToLead::class)->windowOpen($lead))->toBeTrue();
});

it('opts a lead out of WhatsApp when they reply STOP', function (): void {
    $lead = waLead(['phone' => '+923001234567']);

    [$body, $headers] = signedWebhook(inboundPayload('923001234567', 'STOP'));
    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256']], $body);

    expect($lead->fresh()->whatsapp_opted_out_at)->not->toBeNull()
        ->and($lead->fresh()->email_opted_out_at)->toBeNull();
});

it('moves delivery status forward only', function (): void {
    $lead = waLead(['phone' => '+923001234567']);
    $message = Message::create([
        'lead_id' => $lead->id, 'channel' => 'whatsapp', 'direction' => 'outbound',
        'provider_message_id' => 'wamid.OUT1', 'status' => 'sent', 'sent_at' => now(),
    ]);

    foreach (['read', 'delivered'] as $status) {
        [$body, $headers] = signedWebhook(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'statuses' => [['id' => 'wamid.OUT1', 'status' => $status]],
        ]]]]]]);
        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256']], $body);
    }

    expect($message->fresh()->status->value)->toBe('read');
});
