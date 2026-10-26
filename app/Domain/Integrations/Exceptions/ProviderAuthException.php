<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

/**
 * The provider rejected the token: expired, revoked, or missing a permission.
 *
 * Retrying cannot fix this, so the connection is marked for reconnection and
 * the user is shown a Reconnect action instead of a silent failure (§59).
 */
final class ProviderAuthException extends RuntimeException {}
