<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class MissingTenantContextException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No tenant is bound to the current context. Resolve a tenant first, '
            .'or wrap cross-tenant work in TenantContext::withoutScoping().'
        );
    }

    /**
     * @param  class-string  $model
     */
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'Cannot create [%s] without a tenant context. Tenant-owned models '
            .'require a bound tenant so tenant_id can be populated.',
            $model,
        ));
    }
}
