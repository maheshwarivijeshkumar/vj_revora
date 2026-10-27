<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scores replies with Claude, through the Messages API over HTTP.
 *
 * Structured output (a JSON schema) guarantees the reply parses; `fallbacks:
 * "default"` lets the API re-run a request its safety classifiers decline on
 * a model chosen for that decline, inside the same call.
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
final class ClaudeReplyScorer implements ReplyScorer
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2026-10-21';

    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function score(string $reply, ?string $subject = null): ReplyScore
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => self::API_VERSION,
                'anthropic-beta' => self::FALLBACK_BETA,
            ])->timeout(60)->post(self::URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 2048,
                'fallbacks' => 'default',
                'system' => self::INSTRUCTIONS,
                // A one-line classification: low effort is plenty.
                'output_config' => [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'score' => ['type' => 'integer'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['score', 'reason'],
                        'additionalProperties' => false,
                    ]],
                ],
                'messages' => [[
                    'role' => 'user',
                    'content' => ($subject === null ? '' : "Subject: {$subject}\n\n").$reply,
                ]],
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Claude: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Claude returned HTTP '.$response->status().': '.$response->json('error.message'));
        }

        // Checked before reading content: a refusal has nothing to parse.
        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('Claude declined to score this reply.');
        }

        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $parsed = json_decode((string) $block['text'], true);

                if (is_array($parsed) && isset($parsed['score'])) {
                    return ReplyScore::make($parsed['score'], $parsed['reason'] ?? '', 'claude');
                }
            }
        }

        throw new RuntimeException('Claude returned no score.');
    }
}
