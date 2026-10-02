<?php

declare(strict_types=1);

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Tenant isolation
|--------------------------------------------------------------------------
|
| Roadmap task 0.5, and the highest-leverage test in the project. Tenant
| isolation is cheap to enforce now and ruinous to retrofit once dozens of
| models exist, so these tests are written before there is much to protect.
|
| Three layers are covered:
|   1. The Eloquent global scope confines reads and writes to one tenant.
|   2. The scope fails closed when no tenant is bound.
|   3. A schema guard fails the build when a tenant-owned table or model
|      drifts out of the pattern.
|
| Layer 3 is the one that actually holds the line over time: layers 1 and 2
| protect the models that remember to opt in, while layer 3 catches the model
| that forgot — at the moment it is introduced.
|
*/

function bindTenant(Tenant $tenant): void
{
    app(TenantContext::class)->set($tenant);
}

// --- Layer 1: the global scope ---------------------------------------------

it('never returns another tenant\'s records', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $a->id]);
    $userB = User::factory()->create(['tenant_id' => $b->id]);

    bindTenant($a);

    $visible = User::all();

    expect($visible->pluck('id'))->toContain($userA->id)
        ->and($visible->pluck('id'))->not->toContain($userB->id);
});

it('cannot read another tenant\'s record by primary key', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $userB = User::factory()->create(['tenant_id' => $b->id]);

    bindTenant($a);

    expect(User::find($userB->id))->toBeNull();
});

it('stamps the bound tenant onto new records automatically', function (): void {
    $tenant = Tenant::factory()->create();

    bindTenant($tenant);

    $user = User::create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->tenant_id)->toBe($tenant->id);
});

it('refuses to create a tenant-owned record with no tenant bound', function (): void {
    app(TenantContext::class)->forget();

    User::create([
        'name' => 'Orphan',
        'email' => 'orphan@example.com',
        'password' => 'secret-password',
    ]);
})->throws(MissingTenantContextException::class);

it('does not let tenant_id be mass-assigned to another tenant', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    bindTenant($a);

    // BelongsToTenant guards tenant_id, so user input can never steer a record
    // into another tenant. Outside production, Model::shouldBeStrict() turns
    // that into a thrown exception; in production the attribute is silently
    // dropped and the bound tenant is used instead. Both outcomes are safe —
    // the loud one is preferred while developing, which is why it is asserted
    // here.
    User::create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'secret-password',
        'tenant_id' => $b->id,   // attempted override
    ]);
})->throws(MassAssignmentException::class);

it('keeps the bound tenant when tenant_id is passed and strictness is off', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    Model::preventSilentlyDiscardingAttributes(false);
    bindTenant($a);

    $user = User::create([
        'name' => 'Grace Hopper',
        'email' => 'grace2@example.com',
        'password' => 'secret-password',
        'tenant_id' => $b->id,
    ]);

    expect($user->tenant_id)->toBe($a->id)
        ->and($user->fresh()->tenant_id)->toBe($a->id);

    Model::preventSilentlyDiscardingAttributes(true);
});

// --- Layer 2: fail closed ---------------------------------------------------

it('returns nothing rather than everything when no tenant is bound', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    app(TenantContext::class)->forget();

    // The dangerous failure mode is an unscoped query quietly returning every
    // tenant's rows. Missing context must mean "nothing", never "everything".
    expect(User::count())->toBe(0);
});

it('only crosses tenants through the explicit escape hatch', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    User::factory()->create(['tenant_id' => $a->id]);
    User::factory()->create(['tenant_id' => $b->id]);

    bindTenant($a);

    $all = app(TenantContext::class)->withoutScoping(fn () => User::count());

    expect($all)->toBe(2)
        ->and(User::count())->toBe(1);  // scoping restored afterwards
});

it('restores the previous tenant after runAs', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    bindTenant($a);

    $inner = app(TenantContext::class)->runAs($b, fn () => app(TenantContext::class)->id());

    expect($inner)->toBe($b->id)
        ->and(app(TenantContext::class)->id())->toBe($a->id);
});

// --- Layer 3: the schema guard ---------------------------------------------

it('has tenant_id on every tenant-owned table, and the trait on its model', function (): void {
    $models = collect(Finder::create()->files()->in(app_path('Models'))->name('*.php'))
        ->map(fn ($file): string => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Model::class))
        ->map(fn (string $class): Model => new $class);

    // Central records are administered from the platform side and are
    // intentionally not tenant-scoped. Anything not listed here is treated as
    // tenant-owned, so a new model is caught by default rather than missed.
    // 'waitlist_signups' and 'contact_enquiries' hold prospects for the
    // platform itself, captured before any tenant exists, so they are
    // genuinely central rather than an oversight.
    $central = ['tenants', 'tenant_domains', 'platform_users', 'plans',
        'plan_features', 'feature_catalog', 'subscriptions', 'audit_logs',
        'waitlist_signups', 'contact_enquiries'];

    $failures = [];

    foreach ($models as $model) {
        $table = $model->getTable();

        if (in_array($table, $central, true)) {
            continue;
        }

        $hasColumn = DB::getSchemaBuilder()->hasColumn($table, 'tenant_id');
        $hasTrait = in_array(BelongsToTenant::class, class_uses_recursive($model), true);

        if (! $hasColumn) {
            $failures[] = sprintf('%s: table [%s] is missing a tenant_id column.', $model::class, $table);
        }

        if (! $hasTrait) {
            $failures[] = sprintf('%s: model is missing the BelongsToTenant trait.', $model::class);
        }
    }

    expect($failures)->toBeEmpty(
        "Tenant isolation drift detected:\n- ".implode("\n- ", $failures)
        ."\n\nEither add tenant_id and the BelongsToTenant trait, or add the "
        .'table to the $central allowlist in this test if it is genuinely a '
        .'platform-level record.'
    );
});

it('indexes tenant_id on every tenant-owned table', function (): void {
    // Without an index, tenant scoping turns every list query into a full
    // table scan once the table is shared by many tenants.
    $tables = ['users', 'teams', 'team_members', 'usage_meters',
        'usage_records', 'tenant_settings', 'dashboards'];

    $missing = [];

    foreach ($tables as $table) {
        $indexed = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn (object $i): bool => $i->Column_name === 'tenant_id' && (int) $i->Seq_in_index === 1);

        if (! $indexed) {
            $missing[] = $table;
        }
    }

    expect($missing)->toBeEmpty(
        'These tables have no index leading with tenant_id: '.implode(', ', $missing)
    );
});
