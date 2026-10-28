<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Domain\Time\TimezoneCatalog;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'timezone' => 'UTC']);
});

it('labels Pakistan as Islamabad, Karachi', function (): void {
    expect(TimezoneCatalog::label('Asia/Karachi'))->toBe('(UTC+05:00) Islamabad, Karachi')
        ->and(TimezoneCatalog::label('Asia/Kolkata'))->toBe('(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi')
        ->and(TimezoneCatalog::label('Asia/Kathmandu'))->toBe('(UTC+05:45) Kathmandu');
});

it('saves the chosen time zone and shares its label', function (): void {
    $this->actingAs($this->user)
        ->patch('/account/timezone', ['timezone' => 'Asia/Karachi'])
        ->assertRedirect();

    expect($this->user->fresh()->timezone)->toBe('Asia/Karachi');

    $props = $this->actingAs($this->user)->get('/dashboard')->assertOk()->viewData('page')['props'];

    expect($props['auth']['user']['timezone'])->toBe('Asia/Karachi')
        ->and($props['auth']['user']['timezone_label'])->toBe('(UTC+05:00) Islamabad, Karachi');
});

it('rejects a time zone that is not in the list', function (): void {
    $this->actingAs($this->user)
        ->patch('/account/timezone', ['timezone' => 'Not/AZone'])
        ->assertSessionHasErrors('timezone');

    expect($this->user->fresh()->timezone)->toBe('UTC');
});
