<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Scores a reply with the configured AI provider, falling back to keyword
 * rules when none is set up or the call fails, so a reply always gets a
 * score.
 *
 * Provider: AI_REPLY_PROVIDER = claude | openai | gemini | keywords.
 */
final readonly class ScoreReply
{
    public function __construct(
        private KeywordReplyScorer $keywords,
    ) {}

    public function handle(string $reply, ?string $subject = null): ReplyScore
    {
        $provider = $this->provider();

        if ($provider === null) {
            return $this->keywords->score($reply, $subject);
        }

        try {
            return $provider->score($reply, $subject);
        } catch (RuntimeException $e) {
            Log::warning('AI reply scoring failed; used keyword rules instead.', ['error' => $e->getMessage()]);

            return $this->keywords->score($reply, $subject);
        }
    }

    /** The configured provider, or null when it has no key. */
    private function provider(): ?ReplyScorer
    {
        return match (config('services.ai.reply_provider')) {
            'claude' => filled(config('services.anthropic.key')) ? app(ClaudeReplyScorer::class) : null,
            'openai' => filled(config('services.openai.key')) ? app(OpenAiReplyScorer::class) : null,
            'gemini' => filled(config('services.gemini.key')) ? app(GeminiReplyScorer::class) : null,
            default => null,
        };
    }
}
