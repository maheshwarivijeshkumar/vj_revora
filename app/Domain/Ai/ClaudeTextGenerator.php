<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Claude, through the Messages API over HTTP.
 *
 * Adaptive thinking is on by default on this model, so effort is the only
 * dial; a one-page proposal is a medium-effort job. `fallbacks: "default"`
 * lets the API re-run a request its safety classifiers decline on a model
 * chosen for that decline, inside the same call.
 */
final class ClaudeTextGenerator implements TextGenerator
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    public function name(): string
    {
        return 'claude';
    }

    public function generate(string $instructions, string $prompt): string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2026-10-21',
                'anthropic-beta' => 'server-side-fallback-2026-07-01',
            ])->timeout(180)->post(self::URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 16000,
                'fallbacks' => 'default',
                'output_config' => ['effort' => 'medium'],
                'system' => $instructions,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Claude: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Claude returned HTTP '.$response->status().': '.$response->json('error.message'));
        }

        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('Claude declined to write this draft.');
        }

        $text = '';

        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) $block['text'];
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Claude returned no text.');
        }

        return trim($text);
    }
}
