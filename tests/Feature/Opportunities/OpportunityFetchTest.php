<?php

declare(strict_types=1);

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Opportunities\Actions\EnsureServiceProfiles;
use App\Domain\Opportunities\Actions\ImportJobPosts;
use App\Domain\Opportunities\DataObjects\JobPostData;
use App\Domain\Tenancy\TenantContext;
use App\Models\JobPost;
use App\Models\OpportunityMatch;
use App\Models\Tenant;
use App\Models\UpworkAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Fetching job posts
|--------------------------------------------------------------------------
|
| Response shapes match what Remotive, Remote OK, Arbeitnow and Upwork's
| GraphQL API return.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(EnsureServiceProfiles::class)->handle();

    Http::preventStrayRequests();
});

function fakeBoards(array $remotive = [], array $remoteOk = [], array $arbeitnow = []): void
{
    Http::fake([
        'remotive.com/api/remote-jobs' => Http::response(['0-legal-notice' => '…', 'jobs' => $remotive]),
        'remoteok.com/api' => Http::response([['legal' => 'Link back to Remote OK'], ...$remoteOk]),
        'www.arbeitnow.com/api/job-board-api*' => Http::response(['data' => $arbeitnow, 'links' => ['next' => null]]),
    ]);
}

it('pulls the boards, scores the posts and keeps the good fits', function (): void {
    fakeBoards(
        remotive: [[
            'id' => 1, 'url' => 'https://remotive.com/remote-jobs/1', 'title' => 'Laravel developer for our SaaS',
            'company_name' => 'Acme', 'category' => 'Software Development', 'tags' => ['laravel', 'vue'],
            'publication_date' => now()->subDay()->toIso8601String(), 'description' => '<p>Multi-tenant SaaS on Laravel and Vue.</p>',
        ]],
        remoteOk: [[
            'id' => '9', 'position' => 'Account Executive', 'company' => 'Huzzle', 'url' => 'https://remoteok.com/9',
            'epoch' => now()->getTimestamp(), 'tags' => ['sales'], 'description' => 'Sell SaaS.',
        ]],
        arbeitnow: [[
            'slug' => 'on-site-role', 'title' => 'Web developer', 'company_name' => 'Local GmbH', 'remote' => false,
            'url' => 'https://arbeitnow.com/x', 'created_at' => now()->getTimestamp(),
        ]],
    );

    $this->artisan('opportunities:fetch')->assertSuccessful();

    expect(JobPost::query()->pluck('source')->sort()->values()->all())->toBe(['remoteok', 'remotive'])
        ->and(OpportunityMatch::query()->count())->toBe(1);

    $match = OpportunityMatch::query()->sole();
    expect($match->fit_score)->toBeGreaterThanOrEqual(OpportunityMatch::HIGH_FIT)
        ->and($match->opportunity()->title)->toBe('Laravel developer for our SaaS');
});

it('keeps one post when two boards carry the same job', function (): void {
    $post = fn (string $source, string $id): JobPostData => new JobPostData(
        source: $source, externalId: $id, title: 'Senior React Developer', url: "https://{$source}.example/{$id}", company: 'Acme Ltd',
    );

    $first = app(ImportJobPosts::class)->handle([$post('remoteok', '1')]);
    $second = app(ImportJobPosts::class)->handle([$post('remotive', '77'), $post('remoteok', '1')]);

    expect(JobPost::query()->count())->toBe(1)
        ->and($first['created'])->toBe(1)
        ->and($second['duplicates'])->toBe(1)
        ->and($second['updated'])->toBe(1);
});

it('searches Upwork read-only with the profile\'s services and refreshes an expired token', function (): void {
    config(['services.upwork.client_id' => 'up-id', 'services.upwork.client_secret' => 'up-secret']);
    fakeBoards();

    UpworkAccount::create([
        'name' => 'Upwork',
        'credentials' => ['access_token' => 'old', 'refresh_token' => 'refresh-me', 'expires_at' => now()->subMinute()->getTimestamp()],
        'status' => ConnectionStatus::Connected,
    ]);

    Http::fake([
        'www.upwork.com/api/v3/oauth2/token' => Http::response(['access_token' => 'fresh', 'refresh_token' => 'r2', 'expires_in' => 86400]),
        'api.upwork.com/graphql' => Http::response(['data' => ['marketplaceJobPostingsSearch' => [
            'edges' => [['node' => [
                'id' => '1', 'ciphertext' => '~01abc', 'title' => 'Build a Laravel ERP for our warehouse',
                'description' => 'Inventory management, purchase orders, Laravel and Vue.',
                'publishedDateTime' => now()->subHours(3)->toIso8601String(), 'totalApplicants' => 4,
                'amount' => ['rawValue' => '4000', 'currency' => 'USD'],
                'skills' => [['prettyName' => 'Laravel'], ['prettyName' => 'Vue.js']],
                'client' => ['totalSpent' => ['rawValue' => '25000'], 'verificationStatus' => 'VERIFIED', 'location' => ['country' => 'United Kingdom']],
            ]]],
            'pageInfo' => ['hasNextPage' => false],
        ]]]),
    ]);

    $this->artisan('opportunities:fetch --upwork')->assertSuccessful();

    $job = JobPost::query()->where('source', 'upwork')->sole();

    expect($job->url)->toBe('https://www.upwork.com/jobs/~01abc')
        ->and($job->budgetLabel())->toBe('$4,000 fixed')
        ->and($job->signals['payment_verified'])->toBeTrue()
        ->and(UpworkAccount::query()->sole()->credentials['access_token'])->toBe('fresh')
        ->and(OpportunityMatch::query()->sole()->profile->kind)->toBe('freelancer');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.upwork.com/graphql'
        && $request->hasHeader('Authorization', 'Bearer fresh')
        && str_contains((string) $request['query'], 'marketplaceJobPostingsSearch')
        && $request['variables']['filter']['daysPosted_eq'] === 3);
});

it('asks for an Upwork reconnect when the refresh is refused', function (): void {
    config(['services.upwork.client_id' => 'up-id', 'services.upwork.client_secret' => 'up-secret']);
    fakeBoards();

    UpworkAccount::create([
        'credentials' => ['access_token' => 'old', 'refresh_token' => 'revoked', 'expires_at' => now()->subMinute()->getTimestamp()],
        'status' => ConnectionStatus::Connected,
    ]);
    Http::fake(['www.upwork.com/api/v3/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->artisan('opportunities:fetch --upwork')->assertSuccessful();

    expect(UpworkAccount::query()->sole()->status)->toBe(ConnectionStatus::NeedsReconnect);
});
