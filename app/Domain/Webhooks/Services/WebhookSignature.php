<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Services;

/**
 * Signs and verifies a webhook payload (§49).
 *
 * The timestamp is inside the signed string, not merely alongside it, so a
 * captured request cannot be replayed later against the subscriber: moving the
 * timestamp invalidates the signature, and leaving it makes the request fall
 * outside the tolerance window.
 *
 * The scheme is deliberately the one most subscribers already have code for:
 * `t=<unix>,v1=<hex hmac-sha256 of "<t>.<body>">`.
 */
final class WebhookSignature
{
    public const HEADER = 'X-Revora-Signature';

    /**
     * How far out of step a subscriber's clock may be, in seconds.
     *
     * Five minutes is enough for ordinary clock drift and a slow queue without
     * leaving a captured request usable for long.
     */
    public const TOLERANCE = 300;

    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, self::sign($payload, $secret, $timestamp));
    }

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * Verifies a header against a payload.
     *
     * Exposed publicly because it is the reference implementation the developer
     * documentation points at, and because a verifier nobody can run is a
     * verifier nobody trusts.
     */
    public static function verify(
        string $header,
        string $payload,
        string $secret,
        ?int $now = null,
    ): bool {
        $parsed = self::parse($header);

        if ($parsed === null) {
            return false;
        }

        [$timestamp, $signature] = $parsed;

        if (abs(($now ?? time()) - $timestamp) > self::TOLERANCE) {
            return false;
        }

        // Constant time, so a subscriber copying this implementation does not
        // inherit a timing oracle on their own endpoint.
        return hash_equals(self::sign($payload, $secret, $timestamp), $signature);
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private static function parse(string $header): ?array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = $pair;

            match ($key) {
                't' => $timestamp = ctype_digit($value) ? (int) $value : null,
                'v1' => $signature = $value,
                default => null,
            };
        }

        return $timestamp === null || $signature === null || $signature === ''
            ? null
            : [$timestamp, $signature];
    }
}
