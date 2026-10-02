<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Deals\Enums\PipelineEntity;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Platform reference data — safe to re-run, all upserts.
        $this->call([
            PermissionSeeder::class,
            FeatureCatalogSeeder::class,
            PlanSeeder::class,
            DashboardWidgetSeeder::class,
            IntegrationProviderSeeder::class,
        ]);

        if (app()->environment('production')) {
            return;
        }

        $this->seedDemoTenant();
    }

    /**
     * A local development workspace.
     *
     * Mirrors what tenant provisioning (§7) will do in Phase 1, so there is
     * something to sign in to before that job exists.
     */
    private function seedDemoTenant(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Workspace',
                'status' => TenantStatus::Active,
                'provisioned_at' => now(),
            ],
        );

        $plan = Plan::where('key', 'growth')->firstOrFail();

        Subscription::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ],
        );

        // Roles are tenant-scoped (Spatie team mode keyed to tenant_id), so the
        // registrar must be told which tenant we are creating them for.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $owner = Role::findOrCreate('Owner', 'web');
        $owner->syncPermissions(PermissionSeeder::all());

        foreach (['Tenant Admin', 'Sales Manager', 'Sales Representative',
            'Marketing Manager', 'Marketing Executive', 'Customer Support',
            'Operations', 'Viewer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(TenantContext::class)->runAs($tenant, function () use ($tenant, $owner): void {
            $user = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'owner@demo.test'],
                [
                    'name' => 'Demo Owner',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ],
            );

            $user->assignRole($owner);

            $this->seedLeadDefaults();

            // Runs inside the tenant context, so the capture and create
            // pipelines resolve the workspace the same way a request does.
            $this->call(DemoDataSeeder::class);
        });

        $this->command->info('Demo workspace ready — owner@demo.test / password');
    }

    /**
     * The lead sources and scoring rules a new workspace starts with (§7).
     *
     * Tenant provisioning (Phase 1.2) will call the same setup, so a real
     * workspace and the demo one begin identically rather than diverging.
     */
    private function seedLeadDefaults(): void
    {
        $sources = [
            ['key' => 'website', 'name' => 'Website form', 'type' => LeadSourceType::Form],
            ['key' => 'manual', 'name' => 'Entered manually', 'type' => LeadSourceType::Manual],
            ['key' => 'import', 'name' => 'Imported', 'type' => LeadSourceType::Import],
            ['key' => 'api', 'name' => 'API', 'type' => LeadSourceType::Api],
        ];

        foreach ($sources as $source) {
            LeadSource::firstOrCreate(['key' => $source['key']], $source);
        }

        foreach (LeadScorer::defaultRules() as $i => $rule) {
            LeadScoreRule::firstOrCreate(
                ['name' => $rule['name']],
                [...$rule, 'sort_order' => $i],
            );
        }

        $this->seedDefaultPipeline();
    }

    /**
     * The deal board a new workspace starts with (§7, §22).
     */
    private function seedDefaultPipeline(): void
    {
        $pipeline = Pipeline::firstOrCreate(
            ['key' => 'sales'],
            [
                'name' => 'Sales pipeline',
                'entity_type' => PipelineEntity::Deal,
                'is_default' => true,
            ],
        );

        if ($pipeline->stages()->exists()) {
            return;
        }

        foreach (Pipeline::defaultStages() as $i => $stage) {
            // forceCreate because BelongsToTenant guards tenant_id against
            // mass assignment; the stage must land on the pipeline's tenant.
            PipelineStage::forceCreate([
                'tenant_id' => $pipeline->tenant_id,
                'pipeline_id' => $pipeline->id,
                'name' => $stage['name'],
                'key' => $stage['key'],
                'probability' => $stage['probability'],
                'sort_order' => $i,
                'is_won' => $stage['is_won'] ?? false,
                'is_lost' => $stage['is_lost'] ?? false,
                'required_fields' => $stage['required_fields'] ?? null,
            ]);
        }
    }
}
