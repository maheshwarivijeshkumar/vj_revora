<?php

declare(strict_types=1);

use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Messaging\Actions\AdvanceFollowUp;
use App\Domain\Messaging\Actions\SyncMailbox;
use App\Domain\Messaging\Contracts\Mailbox;
use App\Domain\Messaging\DataObjects\IncomingEmail;
use App\Domain\Messaging\DataObjects\OutgoingEmail;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Messaging\Mailboxes\SmtpImapMailbox;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\RunDueFollowUps;
use App\Models\EmailAccount;
use App\Models\FollowUpEnrollment;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Message;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| Follow-up sequence
|--------------------------------------------------------------------------
|
| 1st email at once, 2nd at 6 hours, 3rd at 24 hours; a reply stops it and
| is scored; no reply by 72 hours scores 0/10.
|
*/

/**
 * Stands in for an SMTP + IMAP server: records what was sent, and serves
 * whatever inbox the test puts in it.
 */
final class FakeMailbox implements Mailbox
{
    /** @var list<OutgoingEmail> */
    public array $sent = [];

    /** @var list<IncomingEmail> */
    public array $inbox = [];

    public function send(EmailAccount $account, OutgoingEmail $email): void
    {
        $this->sent[] = $email;
    }

    public function fetchInbox(EmailAccount $account, CarbonInterface $since): iterable
    {
        return $this->inbox;
    }
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    config(['verification.queue_recheck' => false, 'services.ai.reply_provider' => 'keywords']);
    Http::preventStrayRequests();

    $this->mailbox = new FakeMailbox;
    app()->instance(SmtpImapMailbox::class, $this->mailbox);

    $this->account = EmailAccount::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'sales@acme.example']);
    $this->source = LeadSource::factory()->create(['tenant_id' => $this->tenant->id]);
});

function enrolledLead(array $payload, ?LeadSource $source = null): Lead
{
    $normalized = app(LeadNormalizer::class)->normalize($payload);

    return app(CaptureLead::class)->handle($normalized, $source ?? test()->source)->lead;
}

/** Runs whatever follow-up work is due, as the scheduler would. */
function runDueFollowUps(): void
{
    FollowUpEnrollment::query()
        ->where('status', EnrollmentStatus::Active->value)
        ->where('next_run_at', '<=', now())
        ->get()
        ->each(fn (FollowUpEnrollment $e) => app(AdvanceFollowUp::class)->handle($e));
}

it('sends the first email straight away, the second at 6 hours and the third at 24 hours', function (): void {
    $this->freezeTime();
    $lead = enrolledLead(['first_name' => 'Amara', 'email' => 'amara@client.example']);

    runDueFollowUps();
    expect($this->mailbox->sent)->toHaveCount(1)
        ->and($this->mailbox->sent[0]->subject)->toBe('Thanks for your interest, Amara')
        ->and($this->mailbox->sent[0]->textBody)->toContain('Hi Amara,');

    $this->travel(5)->hours();
    runDueFollowUps();
    expect($this->mailbox->sent)->toHaveCount(1);

    $this->travel(1)->hours();
    runDueFollowUps();
    expect($this->mailbox->sent)->toHaveCount(2)
        // Threaded under the first email.
        ->and($this->mailbox->sent[1]->inReplyTo)->toBe($this->mailbox->sent[0]->messageId);

    $this->travel(18)->hours();
    runDueFollowUps();
    expect($this->mailbox->sent)->toHaveCount(3)
        ->and(Message::query()->where('lead_id', $lead->id)->orderBy('id')->pluck('step_position')->all())->toBe([1, 2, 3]);
});

it('scores the lead 0/10 when there is still no reply 72 hours after the first email', function (): void {
    $this->freezeTime();
    $lead = enrolledLead(['first_name' => 'Amara', 'email' => 'amara@client.example']);

    runDueFollowUps();
    $this->travel(6)->hours();
    runDueFollowUps();
    $this->travel(18)->hours();
    runDueFollowUps();

    $this->travel(47)->hours();
    runDueFollowUps();
    expect($lead->fresh()->engagement_score)->toBeNull();

    $this->travel(1)->hours();
    runDueFollowUps();

    $lead->refresh();
    expect($lead->engagement_score)->toBe(0)
        ->and($lead->engagement_reason)->toBe('No reply to 3 follow-up emails')
        ->and(FollowUpEnrollment::query()->sole()->status)->toBe(EnrollmentStatus::NoReply);
});

it('stops the sequence and scores the reply when the lead answers', function (): void {
    $this->freezeTime();
    $lead = enrolledLead(['first_name' => 'Amara', 'email' => 'amara@client.example']);
    runDueFollowUps();

    $this->mailbox->inbox = [new IncomingEmail(
        messageId: 'reply-1@client.example',
        fromAddress: 'amara@client.example',
        fromName: 'Amara',
        subject: 'Re: Thanks for your interest, Amara',
        textBody: "Yes please, can you send me your pricing?\n\nOn Mon, Sales wrote:\n> Hi Amara",
        receivedAt: now(),
        inReplyTo: $this->mailbox->sent[0]->messageId,
    )];

    app(SyncMailbox::class)->handle($this->account);

    $lead->refresh();
    $reply = Message::query()->where('direction', 'inbound')->sole();

    expect(FollowUpEnrollment::query()->sole()->status)->toBe(EnrollmentStatus::Replied)
        ->and($lead->engagement_score)->toBe(9)
        // The quoted thread is not kept as the lead's words.
        ->and($reply->body)->toBe('Yes please, can you send me your pricing?');

    $this->travel(6)->hours();
    runDueFollowUps();
    expect($this->mailbox->sent)->toHaveCount(1);
});

it('files a reply only once however often the inbox is read', function (): void {
    $lead = enrolledLead(['email' => 'amara@client.example']);
    $this->mailbox->inbox = [new IncomingEmail('reply-1@client.example', 'amara@client.example', null, 'Hi', 'Interested', now())];

    app(SyncMailbox::class)->handle($this->account);
    app(SyncMailbox::class)->handle($this->account);

    expect(Message::query()->where('lead_id', $lead->id)->where('direction', 'inbound')->count())->toBe(1);
});

it('ignores inbox mail from people who are not leads', function (): void {
    $this->mailbox->inbox = [new IncomingEmail('invoice@vendor.example', 'billing@vendor.example', null, 'Invoice', 'Please pay', now())];

    app(SyncMailbox::class)->handle($this->account);

    expect(Message::query()->count())->toBe(0);
});

it('does not enrol imported leads, or any lead before a mailbox is connected', function (): void {
    $imported = LeadSource::factory()->imported()->create(['tenant_id' => $this->tenant->id]);
    enrolledLead(['email' => 'cold@list.example'], $imported);

    $this->account->delete();
    enrolledLead(['email' => 'early@client.example']);

    expect(FollowUpEnrollment::query()->count())->toBe(0);
});

it('stops emailing a lead who unsubscribes', function (): void {
    $this->freezeTime();
    $lead = enrolledLead(['email' => 'amara@client.example']);
    runDueFollowUps();

    $this->get($this->mailbox->sent[0]->unsubscribeUrl)->assertOk()->assertSee('unsubscribed');

    $this->travel(6)->hours();
    runDueFollowUps();

    expect($lead->fresh()->email_opted_out_at)->not->toBeNull()
        ->and($this->mailbox->sent)->toHaveCount(1)
        ->and(FollowUpEnrollment::query()->sole()->status)->toBe(EnrollmentStatus::Stopped);
});

it('rejects an unsubscribe link that was tampered with', function (): void {
    $lead = enrolledLead(['email' => 'amara@client.example']);
    $url = URL::signedRoute('unsubscribe', ['lead' => $lead->uuid]).'x';

    $this->get($url)->assertForbidden();

    expect($lead->fresh()->email_opted_out_at)->toBeNull();
});

it('queues due follow-ups for each active workspace from the scheduler', function (): void {
    enrolledLead(['email' => 'amara@client.example']);
    Queue::fake([RunDueFollowUps::class]);

    $this->artisan('messaging:run')->assertSuccessful();

    Queue::assertPushed(RunDueFollowUps::class, fn (RunDueFollowUps $job): bool => $job->tenantId === $this->tenant->id);
});
