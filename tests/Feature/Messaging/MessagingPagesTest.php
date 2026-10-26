<?php

declare(strict_types=1);

use App\Domain\Messaging\Contracts\Mailbox;
use App\Domain\Messaging\DataObjects\OutgoingEmail;
use App\Domain\Messaging\Enums\MailboxProvider;
use App\Domain\Messaging\Mailboxes\SmtpImapMailbox;
use App\Domain\Tenancy\TenantContext;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Email, follow-up and lead conversation pages
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    $permissions = [
        'integration.view', 'integration.connect', 'integration.disconnect', 'integration.sync',
        'template.view', 'template.create', 'template.update', 'template.delete',
        'automation.manage', 'conversation.view', 'message.send', 'lead.view',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('Admin', 'web');
    $role->syncPermissions($permissions);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    Http::preventStrayRequests();

    $this->mailbox = new class implements Mailbox
    {
        /** @var list<OutgoingEmail> */
        public array $sent = [];

        public function send(EmailAccount $account, OutgoingEmail $email): void
        {
            $this->sent[] = $email;
        }

        public function fetchInbox(EmailAccount $account, CarbonInterface $since): iterable
        {
            return [];
        }
    };
    app()->instance(SmtpImapMailbox::class, $this->mailbox);
});

it('keeps the email settings behind the integration permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/settings/email')->assertForbidden();
    $this->actingAs($this->user)->get('/settings/email')->assertOk();
});

it('connects an SMTP mailbox once its login is proven, with the password encrypted', function (): void {
    $this->actingAs($this->user)->post('/settings/email', [
        'email' => 'Sales@Acme.example',
        'from_name' => 'Acme Sales',
        'username' => 'sales@acme.example',
        'password' => 'mailbox-password',
        'smtp_host' => 'mail.acme.example', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'imap_host' => 'mail.acme.example', 'imap_port' => 993, 'imap_encryption' => 'ssl',
    ])->assertRedirect('/settings/email');

    $account = EmailAccount::query()->sole();

    expect($account->email)->toBe('sales@acme.example')
        ->and($account->is_default)->toBeTrue()
        ->and($account->credentials['password'])->toBe('mailbox-password')
        ->and(DB::table('email_accounts')->value('credentials'))->not->toContain('mailbox-password');
});

it('connects a Gmail mailbox through Google sign-in', function (): void {
    config(['services.google.client_id' => 'gid', 'services.google.client_secret' => 'gsecret']);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ga', 'refresh_token' => 'gr', 'expires_in' => 3600]),
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'owner@gmail.example']),
    ]);

    $this->actingAs($this->user)
        ->withSession(['mailbox_oauth_state' => ['provider' => 'google', 'state' => 'abc']])
        ->get('/settings/email/google/callback?code=c0de&state=abc')
        ->assertRedirect('/settings/email');

    $account = EmailAccount::query()->sole();

    expect($account->provider)->toBe(MailboxProvider::Google)
        ->and($account->email)->toBe('owner@gmail.example')
        ->and($account->credentials['refresh_token'])->toBe('gr');
});

it('creates the default schedule of 0, 6 and 24 hours with a 72-hour no-reply window', function (): void {
    $props = $this->actingAs($this->user)->get('/settings/follow-up')->assertOk()->viewData('page')['props'];

    expect(array_column($props['sequence']['steps'], 'delay_hours'))->toEqual([0, 6, 24])
        ->and($props['sequence']['no_reply_after_hours'])->toBe(72)
        ->and($props['templates'])->toHaveCount(3);
});

it('refuses a schedule whose emails do not go out in order', function (): void {
    $this->actingAs($this->user)->get('/settings/follow-up');
    $templates = EmailTemplate::query()->pluck('uuid');

    $this->actingAs($this->user)->put('/settings/follow-up', [
        'is_active' => true,
        'no_reply_after_hours' => 72,
        'steps' => [
            ['delay_hours' => 6, 'template_id' => $templates[0]],
            ['delay_hours' => 0, 'template_id' => $templates[1]],
        ],
    ])->assertSessionHasErrors(['steps' => 'Each email must go out later than the one before it.']);

    $this->actingAs($this->user)->put('/settings/follow-up', [
        'is_active' => false,
        'no_reply_after_hours' => 48,
        'steps' => [
            ['delay_hours' => 0, 'template_id' => $templates[0]],
            ['delay_hours' => 12, 'template_id' => $templates[1]],
        ],
    ])->assertSessionHasNoErrors();

    $sequence = FollowUpSequence::query()->with('steps')->sole();
    expect($sequence->is_active)->toBeFalse()
        ->and($sequence->steps->pluck('delay_minutes')->all())->toBe([0, 720])
        ->and($sequence->no_reply_after_minutes)->toBe(2880);
});

it('saves and edits email templates', function (): void {
    $this->actingAs($this->user)->post('/settings/templates', [
        'name' => 'Pricing reply', 'subject' => 'Our pricing, {{first_name}}', 'body' => 'Hi {{first_name}}, here it is.',
    ])->assertSessionHasNoErrors();

    $template = EmailTemplate::query()->sole();

    $this->actingAs($this->user)->patch("/settings/templates/{$template->uuid}", [
        'name' => 'Pricing', 'subject' => 'Pricing', 'body' => 'Updated',
    ])->assertSessionHasNoErrors();

    expect($template->fresh()->name)->toBe('Pricing');
});

it('returns the lead\'s conversation with templates already filled in', function (): void {
    EmailAccount::factory()->create(['tenant_id' => $this->tenant->id, 'from_name' => 'Acme Sales']);
    EmailTemplate::create(['name' => 'Hello', 'subject' => 'Hi {{first_name}}', 'body' => 'From {{sender_name}}']);
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Amara', 'email' => 'amara@client.example']);

    $json = $this->actingAs($this->user)->getJson("/leads/{$lead->id}/conversation")->assertOk()->json();

    expect($json['email']['can_send'])->toBeTrue()
        ->and($json['email']['templates'][0]['subject'])->toBe('Hi Amara')
        ->and($json['email']['templates'][0]['body'])->toBe('From Acme Sales');
});

it('sends an email from the lead page and records it', function (): void {
    EmailAccount::factory()->create(['tenant_id' => $this->tenant->id]);
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'amara@client.example']);

    $this->actingAs($this->user)
        ->post("/leads/{$lead->id}/conversation/email", ['subject' => 'Quick question', 'body' => 'Are you free Tuesday?'])
        ->assertSessionHas('success');

    expect($this->mailbox->sent)->toHaveCount(1)
        ->and($this->mailbox->sent[0]->to)->toBe('amara@client.example')
        ->and(Message::query()->sole()->user_id)->toBe($this->user->id);
});

it('will not email a lead who unsubscribed, whoever sends it', function (): void {
    EmailAccount::factory()->create(['tenant_id' => $this->tenant->id]);
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'amara@client.example']);
    $lead->forceFill(['email_opted_out_at' => now()])->save();

    $this->actingAs($this->user)
        ->post("/leads/{$lead->id}/conversation/email", ['subject' => 'Hi', 'body' => 'Hello'])
        ->assertSessionHas('error');

    expect($this->mailbox->sent)->toBeEmpty();
});

it('does not show one workspace another workspace\'s lead conversation', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Lead::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->user)->getJson("/leads/{$foreign->id}/conversation")->assertNotFound();
});
