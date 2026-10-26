<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

/**
 * A provider call failed for a reason a later attempt may fix (§59).
 */
final class ProviderRequestException extends RuntimeException {}
