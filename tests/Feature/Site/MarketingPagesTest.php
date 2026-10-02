<?php

declare(strict_types=1);

use App\Models\ContactEnquiry;

/*
|--------------------------------------------------------------------------
| Marketing pages
|--------------------------------------------------------------------------
|
| Every public page renders, and the contact form behaves.
|
*/

it('renders every marketing page', function (string $path, string $component): void {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component)->has('brand'));
})->with([
    ['/home', 'marketing/Home'],
    ['/product', 'marketing/Product'],
    ['/solutions', 'marketing/Solutions'],
    ['/integrations', 'marketing/Integrations'],
    ['/pricing', 'marketing/Pricing'],
    ['/security', 'marketing/Security'],
    ['/developers', 'marketing/Developers'],
    ['/about', 'marketing/About'],
    ['/contact', 'marketing/Contact'],
]);

it('builds the pricing comparison matrix from plan data', function (): void {
    $this->get('/pricing')->assertInertia(function ($page) {
        $matrix = $page->toArray()['props']['matrix'];
        $groups = collect($matrix)->pluck('group');

        expect($groups)->toContain('Capacity', 'Volume', 'Intelligence', 'Platform');

        // Every row must carry one cell per plan, or the table misaligns.
        $planCount = count($page->toArray()['props']['plans']);

        foreach ($matrix as $group) {
            foreach ($group['rows'] as $row) {
                expect($row['values'])->toHaveCount($planCount);
            }
        }
    });
});

// --- Contact form -----------------------------------------------------------

it('records a contact enquiry', function (): void {
    $this->post('/contact', [
        'name' => 'Amara Okafor',
        'email' => 'amara@acme.example',
        'company' => 'Acme',
        'topic' => 'demo',
        'message' => 'We get around 400 leads a month from Meta and lose most of them.',
        'consent' => true,
    ])->assertRedirect();

    $enquiry = ContactEnquiry::firstOrFail();

    expect($enquiry->name)->toBe('Amara Okafor')
        ->and($enquiry->topic)->toBe('demo')
        ->and($enquiry->status)->toBe('new')
        ->and($enquiry->consent)->toBeTrue()
        ->and($enquiry->consent_at)->not->toBeNull();
});

it('validates the contact form on the server', function (): void {
    $this->post('/contact', [])
        ->assertSessionHasErrors(['name', 'email', 'message', 'consent']);

    // A one-word message is not enough to reply usefully.
    $this->post('/contact', [
        'name' => 'A', 'email' => 'a@b.example', 'topic' => 'demo',
        'message' => 'hi', 'consent' => true,
    ])->assertSessionHasErrors('message');

    // Topic must be one of the offered options, not arbitrary input.
    $this->post('/contact', [
        'name' => 'A', 'email' => 'a@b.example', 'topic' => 'whatever',
        'message' => 'A perfectly reasonable enquiry message.', 'consent' => true,
    ])->assertSessionHasErrors('topic');

    expect(ContactEnquiry::count())->toBe(0);
});

it('rejects a contact submission that fills the honeypot', function (): void {
    $this->post('/contact', [
        'name' => 'Bot', 'email' => 'bot@spam.example', 'topic' => 'other',
        'message' => 'Buy cheap backlinks from our website today.',
        'consent' => true, 'website' => 'http://spam.example',
    ])->assertSessionHasErrors('website');

    expect(ContactEnquiry::count())->toBe(0);
});
