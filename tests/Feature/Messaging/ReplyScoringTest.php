<?php

declare(strict_types=1);

use App\Domain\Messaging\Scoring\ScoreReply;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Reply scoring
|--------------------------------------------------------------------------
|
| Claude, OpenAI or Gemini, chosen by AI_REPLY_PROVIDER; keyword rules when
| none is configured or the provider fails.
|
*/

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('scores with keyword rules when no AI provider is configured', function (string $reply, int $score): void {
    config(['services.ai.reply_provider' => 'keywords']);

    expect(app(ScoreReply::class)->handle($reply)->score)->toBe($score);
})->with([
    'asks for pricing' => ['Can you send me your pricing?', 9],
    'interested' => ['Sounds interesting, tell me more', 7],
    'later' => ['Not now, maybe later in the year', 3],
    'not interested' => ['No thanks, not interested in a demo', 1],
    'unclear' => ['Received.', 5],
]);

it('scores with Claude using structured output and the default refusal fallback', function (): void {
    config([
        'services.ai.reply_provider' => 'claude',
        'services.anthropic.key' => 'test-key',
        'services.anthropic.model' => 'claude-opus-5-5',
    ]);

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'stop_reason' => 'end_turn',
        'content' => [
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => '{"score": 8, "reason": "Asked for a demo next week"}'],
        ],
    ])]);

    $score = app(ScoreReply::class)->handle('Could we do a demo next week?');

    expect($score->score)->toBe(8)
        ->and($score->reason)->toBe('Asked for a demo next week')
        ->and($score->scoredBy)->toBe('claude');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2026-10-21')
        && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
        && $request['model'] === 'claude-opus-5-5'
        && $request['fallbacks'] === 'default'
        && $request['output_config']['format']['type'] === 'json_schema');
});

it('falls back to keyword rules when Claude declines or fails', function (array $response, int $status): void {
    config(['services.ai.reply_provider' => 'claude', 'services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/v1/messages' => Http::response($response, $status)]);

    $score = app(ScoreReply::class)->handle('Can you send me your pricing?');

    expect($score->scoredBy)->toBe('keywords')
        ->and($score->score)->toBe(9);
})->with([
    'refusal' => [['stop_reason' => 'refusal', 'content' => []], 200],
    'server error' => [['error' => ['message' => 'Overloaded']], 529],
]);

it('scores with OpenAI', function (): void {
    config(['services.ai.reply_provider' => 'openai', 'services.openai.key' => 'sk-test', 'services.openai.model' => 'gpt-test']);
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => '{"score": 6, "reason": "Asked a question"}']]],
    ])]);

    $score = app(ScoreReply::class)->handle('How does onboarding work?');

    expect($score->score)->toBe(6)->and($score->scoredBy)->toBe('openai');
    Http::assertSent(fn (Request $request): bool => $request['model'] === 'gpt-test'
        && $request['response_format']['json_schema']['strict'] === true);
});

it('scores with Gemini', function (): void {
    config(['services.ai.reply_provider' => 'gemini', 'services.gemini.key' => 'g-test', 'services.gemini.model' => 'gemini-test']);
    Http::fake(['generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => '{"score": 10, "reason": "Ready to sign"}']]]]],
    ])]);

    $score = app(ScoreReply::class)->handle('Send the contract, we are ready to sign.');

    expect($score->score)->toBe(10)->and($score->scoredBy)->toBe('gemini');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'g-test'));
});

it('keeps an AI score within 1 to 10', function (): void {
    config(['services.ai.reply_provider' => 'openai', 'services.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => '{"score": 42, "reason": "Very keen"}']]],
    ])]);

    expect(app(ScoreReply::class)->handle('Yes!')->score)->toBe(10);
});
