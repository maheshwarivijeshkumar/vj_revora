<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scores replies with OpenAI's Chat Completions API and a strict JSON schema.
 */
final class OpenAiReplyScorer implements ReplyScorer
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    public function score(string $reply, ?string $subject = null): ReplyScore
    {
        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(60)
                ->post(self::URL, [
                    'model' => config('services.openai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => self::INSTRUCTIONS],
                        ['role' => 'user', 'content' => ($subject === null ? '' : "Subject: {$subject}\n\n").$reply],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'reply_score',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'score' => ['type' => 'integer'],
                                    'reason' => ['type' => 'string'],
                                ],
                                'required' => ['score', 'reason'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach OpenAI: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI returned HTTP '.$response->status().': '.$response->json('error.message'));
        }

        $parsed = json_decode((string) $response->json('choices.0.message.content'), true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new RuntimeException('OpenAI returned no score.');
        }

        return ReplyScore::make($parsed['score'], $parsed['reason'] ?? '', 'openai');
    }
}
