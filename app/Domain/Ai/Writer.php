<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * The configured text generator, or none.
 *
 * AI_PROVIDER = claude | openai | gemini. Without a key for the chosen
 * provider there is no generator, and callers fall back to templates.
 */
final class Writer
{
    public function generator(): ?TextGenerator
    {
        return match (config('services.ai.provider')) {
            'claude' => filled(config('services.anthropic.key')) ? app(ClaudeTextGenerator::class) : null,
            'openai' => filled(config('services.openai.key')) ? app(OpenAiTextGenerator::class) : null,
            'gemini' => filled(config('services.gemini.key')) ? app(GeminiTextGenerator::class) : null,
            default => null,
        };
    }
}
