<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scores replies with Google Gemini's generateContent and a response schema.
 */
final class GeminiReplyScorer implements ReplyScorer
{
    public function score(string $reply, ?string $subject = null): ReplyScore
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .rawurlencode((string) config('services.gemini.model')).':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->timeout(60)
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => self::INSTRUCTIONS]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => ($subject === null ? '' : "Subject: {$subject}\n\n").$reply]],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'score' => ['type' => 'INTEGER'],
                                'reason' => ['type' => 'STRING'],
                            ],
                            'required' => ['score', 'reason'],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Gemini: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gemini returned HTTP '.$response->status().': '.$response->json('error.message'));
        }

        $parsed = json_decode((string) $response->json('candidates.0.content.parts.0.text'), true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new RuntimeException('Gemini returned no score.');
        }

        return ReplyScore::make($parsed['score'], $parsed['reason'] ?? '', 'gemini');
    }
}
