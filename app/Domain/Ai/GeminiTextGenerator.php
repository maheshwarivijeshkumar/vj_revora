<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini's generateContent.
 */
final class GeminiTextGenerator implements TextGenerator
{
    public function name(): string
    {
        return 'gemini';
    }

    public function generate(string $instructions, string $prompt): string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .rawurlencode((string) config('services.gemini.model')).':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->timeout(180)
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $instructions]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Gemini: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gemini returned HTTP '.$response->status().': '.$response->json('error.message'));
        }

        $text = '';

        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        if (trim($text) === '') {
            throw new RuntimeException('Gemini returned no text.');
        }

        return trim($text);
    }
}
