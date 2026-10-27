<?php

declare(strict_types=1);

use App\Domain\Opportunities\Actions\EnsureServiceProfiles;
use App\Domain\Opportunities\FitScorer;
use App\Domain\Tenancy\TenantContext;
use App\Models\JobPost;
use App\Models\ServiceProfile;
use App\Models\Tenant;
use App\Models\Tender;

/*
|--------------------------------------------------------------------------
| Fit scoring
|--------------------------------------------------------------------------
|
| Real-shaped tenders and posts against the Berg Media Digital and Vijesh
| profiles, including the false positives found on live PPRA and job data.
|
*/

beforeEach(function (): void {
    app(TenantContext::class)->set(Tenant::factory()->create());
    app(EnsureServiceProfiles::class)->handle();

    $this->agency = ServiceProfile::query()->where('kind', 'agency')->sole();
    $this->freelancer = ServiceProfile::query()->where('kind', 'freelancer')->sole();
    $this->scorer = app(FitScorer::class);
});

function fitTender(string $title, string $category = 'Consultancy Services'): Tender
{
    return Tender::factory()->make(['title' => $title, 'procurement_category' => $category, 'description' => null, 'closing_at' => now()->addDays(10)]);
}

function fitJob(string $title, string $description = '', array $attributes = []): JobPost
{
    return new JobPost([
        'source' => 'remoteok', 'external_id' => '1', 'title' => $title, 'description' => $description,
        'url' => 'https://example.com', 'fingerprint' => 'x', 'posted_at' => now()->subDay(),
        'first_seen_at' => now(), 'last_seen_at' => now(), ...$attributes,
    ]);
}

it('rates a software services tender as a strong fit', function (): void {
    $fit = $this->scorer->score($this->agency, fitTender('PROCUREMENT OF ERP MANAGEMENT SYSTEM FOR HR AND ACCOUNTS'));

    expect($fit->score)->toBeGreaterThanOrEqual(60)
        ->and($fit->services)->toContain('Custom Software Development');
});

it('keeps hardware purchases out, however IT they sound', function (): void {
    $fit = $this->scorer->score($this->agency, fitTender('Procurement of Data Center Graded Switches and laptops', 'Goods'));

    expect($fit->score)->toBeLessThan(35);
});

it('counts plurals, so "web applications" asks for a web application', function (): void {
    $fit = $this->scorer->score($this->agency, fitTender('VAPT for Network Infrastructure and Web Applications'));

    expect($fit->services)->toContain('Web Development');
});

it('reads AI, POS and MIS only as capitalised words', function (): void {
    expect($this->scorer->score($this->agency, fitJob('Maintain office supplies'))->services)->toBe([])
        ->and($this->scorer->score($this->agency, fitJob('Build an AI chatbot for support'))->services)->toContain('AI & Automation');
});

it('ignores a closed tender', function (): void {
    $tender = fitTender('Development of web portal');
    $tender->closing_at = now()->subDay();

    expect($this->scorer->score($this->agency, $tender)->score)->toBe(0);
});

it('marks down hires a services business cannot fill', function (string $title): void {
    expect($this->scorer->score($this->agency, fitJob($title))->score)->toBeLessThan(35);
})->with([
    'sales' => ['Head of Sales, AI startup'],
    'trainer' => ['AI Trainer - Video Annotation'],
    'unpaid' => ['Unpaid internship: build our website'],
]);

it('marks down German-language roles', function (): void {
    $english = $this->scorer->score($this->agency, fitJob('Senior Laravel Developer', 'Build our SaaS platform.'));
    $german = $this->scorer->score($this->agency, fitJob('Senior Laravel Developer (m/w/d)', 'Build our SaaS platform.'));

    expect($german->score)->toBe($english->score - 20);
});

it('gives a Laravel job more technology fit for the Laravel engineer', function (): void {
    $job = fitJob('Laravel and Vue developer for a multi-tenant SaaS', 'PHP, MySQL, Stripe billing, REST API.');

    expect($this->scorer->score($this->freelancer, $job)->skillHits)
        ->toBeGreaterThan($this->scorer->score($this->agency, $job)->skillHits);
});
