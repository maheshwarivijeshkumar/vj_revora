<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI's Chat Completions API.
 */
final class OpenAiTextGenerator implements TextGenerator
{
    public function name(): string
    {
        return 'openai';
    }

    public function generate(string $instructions, string $prompt): string
    {
        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(180)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $instructions],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach OpenAI: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI returned HTTP '.$response->status().': '.$response->json('error.message'));
        }

        $text = trim((string) $response->json('choices.0.message.content'));

        if ($text === '') {
            throw new RuntimeException('OpenAI returned no text.');
        }

        return $text;
    }
}
