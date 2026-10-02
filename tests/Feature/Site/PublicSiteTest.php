<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\WaitlistSignup;
use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
|
| Coming soon, landing page, brand preview, waitlist capture and the branded
| error pages.
|
*/

// --- Site mode --------------------------------------------------------------

it('serves the coming-soon page at the root before launch', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ComingSoon'));
});

it('keeps the landing page reachable while still in coming-soon mode', function (): void {
    // So marketing copy can be reviewed without exposing it publicly.
    $this->get('/home')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('marketing/Home'));
});

// --- Landing ----------------------------------------------------------------

it('builds pricing from the plans table rather than hard-coded copy', function (): void {
    $plan = Plan::factory()->create([
        'name' => 'Regression Plan',
        'price' => 123,
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 0,
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'users', 'value' => 7, 'is_unlimited' => false],
        ['feature_key' => 'automation', 'value' => true, 'is_unlimited' => false],
        ['feature_key' => 'leads_per_month', 'value' => true, 'is_unlimited' => true],
        ['feature_key' => 'white_label', 'value' => false, 'is_unlimited' => false],
    ]);

    $this->get('/home')->assertInertia(function ($page) {
        $plans = collect($page->toArray()['props']['plans']);
        $found = $plans->firstWhere('name', 'Regression Plan');

        expect($found)->not->toBeNull()
            ->and((float) $found['price'])->toBe(123.0);

        $by = collect($found['highlights'])->keyBy('label');

        // A numeric cap renders as a number, an unlimited quota as "Unlimited",
        // and a boolean capability as "Included" — never as "Unlimited", which
        // would imply a quota that does not exist.
        expect($by['Users']['value'])->toBe('7')
            ->and($by['Leads / month']['value'])->toBe('Unlimited')
            ->and($by['Automation workflows']['value'])->toBe('Included')
            ->and($by['White label']['value'])->toBe('—')
            ->and($by['White label']['included'])->toBeFalse();
    });
});

it('hides plans that are not active or not public', function (): void {
    Plan::factory()->create(['name' => 'Hidden Plan', 'is_public' => false]);
    Plan::factory()->create(['name' => 'Retired Plan', 'is_active' => false]);

    $this->get('/home')->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['plans'])->pluck('name');

        expect($names)->not->toContain('Hidden Plan')
            ->and($names)->not->toContain('Retired Plan');
    });
});

// --- Waitlist ---------------------------------------------------------------

it('captures a waitlist signup with consent and attribution', function (): void {
    $this->post('/waitlist', [
        'email' => 'Founder@Example.com ',
        'consent' => true,
        'utm_source' => 'linkedin',
        'utm_campaign' => 'launch',
        'landing_page' => 'https://example.test/?utm_source=linkedin',
    ])->assertRedirect();

    $signup = WaitlistSignup::firstOrFail();

    // Laravel's TrimStrings middleware strips the trailing space before
    // validation ever sees it.
    expect($signup->email)->toBe('Founder@Example.com')
        // Normalised separately so the unique index actually collapses
        // case and whitespace variants of the same address.
        ->and($signup->email_normalized)->toBe('founder@example.com')
        ->and($signup->consent)->toBeTrue()
        ->and($signup->consent_at)->not->toBeNull()
        ->and($signup->utm)->toMatchArray(['utm_source' => 'linkedin']);
});

it('treats a repeat signup as a no-op rather than a duplicate', function (): void {
    foreach (['me@example.com', 'ME@Example.com'] as $email) {
        $this->post('/waitlist', ['email' => $email, 'consent' => true])
            ->assertRedirect();
    }

    expect(WaitlistSignup::count())->toBe(1);
});

it('requires a valid email and explicit consent', function (): void {
    $this->post('/waitlist', ['email' => 'not-an-email', 'consent' => true])
        ->assertSessionHasErrors('email');

    $this->post('/waitlist', ['email' => 'ok@example.com', 'consent' => false])
        ->assertSessionHasErrors('consent');

    expect(WaitlistSignup::count())->toBe(0);
});

it('rejects a submission that fills the honeypot', function (): void {
    $this->post('/waitlist', [
        'email' => 'bot@example.com',
        'consent' => true,
        'website' => 'http://spam.example',
    ])->assertSessionHasErrors('website');

    expect(WaitlistSignup::count())->toBe(0);
});

// --- Brand preview ----------------------------------------------------------

it('previews a brand for the session without changing the default', function (): void {
    $this->get('/branding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Brand')
            ->where('active', 'revora')
            ->has('brands', 8)
        );

    $this->post('/branding', ['brand' => 'leadforge'])->assertRedirect();

    $this->get('/branding')->assertInertia(fn ($page) => $page
        ->where('active', 'leadforge')
        // The configured default is untouched — previewing re-themes only
        // this session.
        ->where('configured', 'revora')
    );
});

it('passes the home url so the client does not infer it from site mode', function (): void {
    // The "see it on the home page" toast action needs to know whether the
    // marketing home lives at / or /home, which SITE_MODE decides.
    Config::set('site.mode', 'coming_soon');
    $this->get('/branding')->assertInertia(fn ($page) => $page->where('homeUrl', '/home'));

    Config::set('site.mode', 'live');
    $this->get('/branding')->assertInertia(fn ($page) => $page->where('homeUrl', '/'));
});

it('gives the brand picker what the public site shell needs', function (): void {
    // The page renders inside MarketingLayout so it carries the same header
    // nav as every other public page, which needs these props.
    $this->get('/branding')->assertInertia(fn ($page) => $page
        ->has('brand.name')
        ->has('brand.assets')
        ->has('contact')
        ->has('social')
    );
});

it('does not flash a success message when a brand is applied', function (): void {
    // The client raises that toast itself so it can attach a navigation
    // action. A server flash as well would produce two toasts.
    $this->post('/branding', ['brand' => 'leadforge'])
        ->assertRedirect()
        ->assertSessionMissing('success');
});

it('ignores an unknown brand', function (): void {
    $this->post('/branding', ['brand' => 'not-a-brand'])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('hides the brand picker entirely when previewing is disabled', function (): void {
    Config::set('brand.allow_preview', false);

    $this->get('/branding')->assertNotFound();
    $this->post('/branding', ['brand' => 'leadforge'])->assertNotFound();
});

// --- Manifest ---------------------------------------------------------------

it('generates the PWA manifest from the active brand', function (): void {
    $this->getJson('/site.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', 'Revora')
        ->assertJsonPath('theme_color', '#059669');
});

// --- Error pages ------------------------------------------------------------

it('renders a branded error page instead of a server default', function (): void {
    // Debug mode deliberately bypasses branded errors, because the exception
    // page is far more useful while developing.
    Config::set('app.debug', false);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('authenticated', false)
            ->has('brand')
        );
});

it('leaves API errors as JSON', function (): void {
    Config::set('app.debug', false);

    // An API client must never receive an HTML error page.
    $this->getJson('/api/v1/no-such-endpoint')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json');
});

it('keeps the exception page in debug mode', function (): void {
    Config::set('app.debug', true);

    $response = $this->get('/no-such-page');

    $response->assertNotFound();
    expect($response->headers->get('x-inertia'))->toBeNull();
});
