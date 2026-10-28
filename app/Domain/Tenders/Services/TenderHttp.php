<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The HTTP client every government feed shares.
 *
 * Retries a dropped connection, a 5xx and a 429. A 4xx other than 429 is the
 * portal refusing the request, and retrying it will not change the answer.
 */
final class TenderHttp
{
    /**
     * @return array<string, mixed>
     */
    public static function json(string $url): array
    {
        try {
            $body = Http::withUserAgent('RevoraCRM tender monitor (+'.config('app.url').')')
                ->connectTimeout(10)
                ->timeout(60)
                ->retry(2, 2000, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                })
                ->get($url)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('Could not read '.$url.': '.$e->getMessage(), previous: $e);
        }

        if (! is_array($body)) {
            throw new RuntimeException('Could not read '.$url.': the response was not JSON.');
        }

        /** @var array<string, mixed> $body */
        return $body;
    }
}
