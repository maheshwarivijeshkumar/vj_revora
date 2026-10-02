<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The granular permission catalog from §68.
 *
 * Permissions are global; *roles* are tenant-scoped (Spatie team mode keyed to
 * tenant_id), so each tenant composes its own roles from this shared vocabulary
 * during provisioning.
 */
final class PermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const GROUPS = [
        'lead' => ['view', 'create', 'update', 'delete', 'export', 'import', 'assign', 'merge'],
        'contact' => ['view', 'create', 'update', 'delete', 'export'],
        'company' => ['view', 'create', 'update', 'delete', 'export'],
        'deal' => ['view', 'create', 'update', 'delete', 'export'],
        'pipeline' => ['view', 'manage'],
        'quote' => ['view', 'create', 'update', 'delete'],
        'product' => ['view', 'create', 'update', 'delete'],
        'task' => ['view', 'create', 'update', 'delete'],
        'campaign' => ['view', 'create', 'manage'],
        'automation' => ['view', 'create', 'execute', 'manage'],
        'conversation' => ['view', 'reply', 'assign', 'close'],
        'message' => ['send'],
        'template' => ['view', 'create', 'update', 'delete'],
        'form' => ['view', 'create', 'update', 'delete'],
        'appointment' => ['view', 'create', 'update', 'cancel'],
        'calendar' => ['connect'],
        'ai' => ['view', 'configure', 'execute', 'killswitch'],
        'integration' => ['view', 'connect', 'disconnect', 'sync'],
        'crm' => ['view', 'connect', 'map', 'sync'],
        'report' => ['view', 'create', 'schedule', 'export'],
        'dashboard' => ['view', 'configure', 'share'],
        'api' => ['view', 'create', 'revoke'],
        'webhook' => ['view', 'manage'],
        'billing' => ['view', 'manage'],
        'user' => ['view', 'create', 'update', 'delete', 'invite'],
        'role' => ['view', 'create', 'update', 'delete'],
        'team' => ['view', 'create', 'update', 'delete'],
        'custom_field' => ['view', 'manage'],
        'settings' => ['view', 'manage'],
        'audit' => ['view'],
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $entity => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$entity}.{$action}", 'web');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Every permission key, used by tenant provisioning to grant the Owner role.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $keys = [];

        foreach (self::GROUPS as $entity => $actions) {
            foreach ($actions as $action) {
                $keys[] = "{$entity}.{$action}";
            }
        }

        return $keys;
    }
}
