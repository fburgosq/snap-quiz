<?php

declare(strict_types=1);

use App\Services\Claude\Exceptions\ClaudeApiException;
use App\Services\Claude\Exceptions\ClaudeException;
use App\Services\Claude\Exceptions\InvalidClaudeResponseException;
use App\Services\Claude\Exceptions\InvalidImageException;
use App\Services\Claude\ExerciseExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config([
        'services.anthropic.key' => 'test-anthropic-key',
        'services.anthropic.base_url' => 'https://api.anthropic.com',
        'services.anthropic.version' => '2023-06-01',
        'services.anthropic.model' => 'claude-sonnet-5-5',
        'services.anthropic.max_tokens' => 8192,
        'services.anthropic.timeout' => 120,
        'services.anthropic.max_image_bytes' => 5 * 1024 * 1024,
        'services.anthropic.resize_max_edge' => 1568,
    ]);
});

/**
 * @param  array<string, mixed>|string  $contentText
 * @return array<string, mixed>
 */
function claudeSuccessBody(array|string $contentText = [
    'title' => 'World Capitals',
    'exercise' => [
        [
            'sentence' => 'The capital of France is ____.',
            'correct' => 'Paris',
            'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
            'reason' => 'Paris is the capital and largest city of France.',
        ],
    ],
], string $stopReason = 'end_turn'): array
{
    $text = is_array($contentText)
        ? (string) json_encode($contentText, JSON_THROW_ON_ERROR)
        : $contentText;

    return [
        'id' => 'msg_01Test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => $stopReason,
        'content' => [
            [
                'type' => 'text',
                'text' => $text,
            ],
        ],
        'usage' => [
            'input_tokens' => 420,
            'output_tokens' => 115,
        ],
    ];
}

test('extracts exercises from an uploaded image and sends expected headers, prompt, and payload', function (): void {
    Http::preventStrayRequests();
    Log::spy();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(
            claudeSuccessBody(),
            200,
            ['request-id' => 'req_abc123'],
        ),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 200, 150);
    $extractor = app(ExerciseExtractor::class);

    $result = $extractor->extract($image);

    expect($result)->toBe([
        'title' => 'World Capitals',
        'exercise' => [
            [
                'sentence' => 'The capital of France is ____.',
                'correct' => 'Paris',
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
                'reason' => 'Paris is the capital and largest city of France.',
            ],
        ],
    ]);

    $expectedPrompt = file_get_contents(resource_path('prompts/fill-in-the-blank-extractor.txt'));

    Http::assertSent(function (Request $request) use ($expectedPrompt): bool {
        $data = $request->data();
        $content = $data['messages'][0]['content'] ?? [];

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->method() === 'POST'
            && $request->hasHeader('x-api-key', 'test-anthropic-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && str_contains($request->header('content-type')[0] ?? '', 'application/json')
            && $data['model'] === 'claude-sonnet-5-5'
            && $data['max_tokens'] === 8192
            && $data['system'] === $expectedPrompt
            && ! array_key_exists('temperature', $data)
            && ! array_key_exists('top_p', $data)
            && count($data['messages']) === 1
            && ($content[0]['type'] ?? null) === 'image'
            && ($content[0]['source']['type'] ?? null) === 'base64'
            && ($content[0]['source']['media_type'] ?? null) === 'image/png'
            && is_string($content[0]['source']['data'] ?? null)
            && $content[0]['source']['data'] !== ''
            && ($content[1]['type'] ?? null) === 'text'
            && ($content[1]['text'] ?? null) === 'Analyze the attached image and return the JSON object exactly as specified.';
    });

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Claude exercise extraction completed.'
            && $context['model'] === 'claude-sonnet-5-5'
            && $context['input_tokens'] === 420
            && $context['output_tokens'] === 115
            && $context['request_id'] === 'req_abc123'
            && $context['exercises_count'] === 1
            && is_int($context['duration_ms']));
});

test('extracts exercises when given a local file path and respects configured model', function (): void {
    Http::preventStrayRequests();
    config(['services.anthropic.model' => 'claude-haiku-4-5']);

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(claudeSuccessBody()),
    ]);

    $image = UploadedFile::fake()->image('worksheet.jpg', 100, 100);
    $path = (string) $image->getRealPath();

    $result = app(ExerciseExtractor::class)->extract($path);

    expect($result['title'])->toBe('World Capitals');

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'claude-haiku-4-5'
        && $request['messages'][0]['content'][0]['source']['media_type'] === 'image/jpeg');
});

test('parses JSON wrapped in markdown code fences', function (): void {
    Http::preventStrayRequests();

    $fencedJson = "```json\n".json_encode([
        'title' => 'Grammar Quiz',
        'exercise' => [
            [
                'sentence' => 'She ____ to school every day and ____ her friends.',
                'correct' => ['walks', 'meets'],
                'incorrect' => ['walk', 'walking', 'walked', 'to walk', 'meet', 'meeting', 'met', 'to meet'],
                'reason' => 'Present simple third-person singular requires -s on both verbs.',
            ],
        ],
    ], JSON_PRETTY_PRINT)."\n```";

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(claudeSuccessBody($fencedJson)),
    ]);

    $image = UploadedFile::fake()->image('grammar.png', 120, 120);
    $result = app(ExerciseExtractor::class)->extract($image);

    expect($result['title'])->toBe('Grammar Quiz')
        ->and($result['exercise'][0]['correct'])->toBe(['walks', 'meets']);
});

test('retries once when JSON is invalid and throws InvalidClaudeResponseException if second response is also invalid', function (): void {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::sequence()
            ->push(claudeSuccessBody('not valid json {'))
            ->push(claudeSuccessBody('still not valid json {')),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);

    expect(fn (): array => app(ExerciseExtractor::class)->extract($image))
        ->toThrow(InvalidClaudeResponseException::class);

    Http::assertSentCount(2);
});

test('recovers when first response has invalid JSON and second response has valid JSON', function (): void {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::sequence()
            ->push(claudeSuccessBody('broken json'))
            ->push(claudeSuccessBody()),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);
    $result = app(ExerciseExtractor::class)->extract($image);

    expect($result['title'])->toBe('World Capitals');
    Http::assertSentCount(2);
});

test('retries on HTTP 429 and succeeds when subsequent attempt returns 200', function (): void {
    Http::preventStrayRequests();
    Sleep::fake();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::sequence()
            ->push([
                'type' => 'error',
                'error' => [
                    'type' => 'rate_limit_error',
                    'message' => 'Rate limit exceeded.',
                ],
            ], 429, ['retry-after' => '2'])
            ->push(claudeSuccessBody(), 200),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);
    $result = app(ExerciseExtractor::class)->extract($image);

    expect($result['title'])->toBe('World Capitals');
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('throws ClaudeApiException on HTTP 400 without retrying', function (): void {
    Http::preventStrayRequests();
    Sleep::fake();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'messages.0.content.0.image: invalid base64 data',
            ],
        ], 400, ['request-id' => 'req_bad400']),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);

    try {
        app(ExerciseExtractor::class)->extract($image);
        $this->fail('Expected ClaudeApiException was not thrown.');
    } catch (ClaudeApiException $exception) {
        expect($exception)->toBeInstanceOf(ClaudeException::class)
            ->and($exception->statusCode)->toBe(400)
            ->and($exception->status)->toBe(400)
            ->and($exception->errorType)->toBe('invalid_request_error')
            ->and($exception->requestId)->toBe('req_bad400')
            ->and($exception->getMessage())->toBe('messages.0.content.0.image: invalid base64 data')
            ->and($exception->getMessage())->not->toContain('test-anthropic-key');
    }

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

test('throws InvalidClaudeResponseException when stop_reason is max_tokens', function (): void {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(
            claudeSuccessBody('{"title": "Truncated', 'max_tokens'),
        ),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);

    expect(fn (): array => app(ExerciseExtractor::class)->extract($image))
        ->toThrow(InvalidClaudeResponseException::class, 'max_tokens');

    Http::assertSentCount(1);
});

test('throws InvalidImageException for unsupported MIME types and oversized images', function (): void {
    Http::preventStrayRequests();

    $fakeTextDisguisedAsJpg = UploadedFile::fake()->createWithContent('malicious.jpg', 'plain text content, not an image');

    expect(fn (): array => app(ExerciseExtractor::class)->extract($fakeTextDisguisedAsJpg))
        ->toThrow(InvalidImageException::class, 'Unsupported image MIME type');

    config(['services.anthropic.max_image_bytes' => 10]);
    $oversizedImage = UploadedFile::fake()->image('large.png', 200, 200);

    expect(fn (): array => app(ExerciseExtractor::class)->extract($oversizedImage))
        ->toThrow(InvalidImageException::class, 'exceeds the maximum allowed size');

    Http::assertNothingSent();
});

test('downscales images whose longest edge exceeds resize_max_edge', function (): void {
    Http::preventStrayRequests();
    config(['services.anthropic.resize_max_edge' => 100]);

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(claudeSuccessBody()),
    ]);

    $largeImage = UploadedFile::fake()->image('highres.png', 400, 200);
    app(ExerciseExtractor::class)->extract($largeImage);

    Http::assertSent(function (Request $request): bool {
        $base64 = $request['messages'][0]['content'][0]['source']['data'] ?? '';
        $binary = base64_decode((string) $base64, true);

        if (! is_string($binary)) {
            return false;
        }

        $dimensions = getimagesizefromstring($binary);

        return is_array($dimensions) && $dimensions[0] === 100 && $dimensions[1] === 50;
    });
});

test('throws InvalidClaudeResponseException when required keys are missing or invalid', function (array $invalidPayload): void {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(claudeSuccessBody($invalidPayload)),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);

    expect(fn (): array => app(ExerciseExtractor::class)->extract($image))
        ->toThrow(InvalidClaudeResponseException::class);
})->with([
    'missing title' => [[
        'exercise' => [],
    ]],
    'empty title' => [[
        'title' => '   ',
        'exercise' => [],
    ]],
    'missing exercise array' => [[
        'title' => 'Valid Title',
    ]],
    'exercise missing sentence' => [[
        'title' => 'Valid Title',
        'exercise' => [
            [
                'correct' => 'Paris',
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
                'reason' => 'Capital of France.',
            ],
        ],
    ]],
    'exercise with empty correct array' => [[
        'title' => 'Valid Title',
        'exercise' => [
            [
                'sentence' => 'The capital of France is ____.',
                'correct' => [],
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
                'reason' => 'Capital of France.',
            ],
        ],
    ]],
    'exercise missing reason' => [[
        'title' => 'Valid Title',
        'exercise' => [
            [
                'sentence' => 'The capital of France is ____.',
                'correct' => 'Paris',
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
            ],
        ],
    ]],
]);

test('logs soft warnings without throwing when blanks or distractors violate soft rules', function (): void {
    Http::preventStrayRequests();
    Log::spy();

    $payloadWithSoftIssues = [
        'title' => 'Geography Quiz',
        'exercise' => [
            [
                'sentence' => 'The capital of France is Paris.',
                'correct' => 'Paris',
                'incorrect' => ['Paris', 'Lyon', 'Marseille'],
                'reason' => 'Paris is the capital of France.',
            ],
        ],
    ];

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(claudeSuccessBody($payloadWithSoftIssues)),
    ]);

    $image = UploadedFile::fake()->image('worksheet.png', 100, 100);
    $result = app(ExerciseExtractor::class)->extract($image);

    expect($result)->toBe($payloadWithSoftIssues);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'no "____" blanks'))
        ->once();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'does not match 4x'))
        ->once();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'equals a correct answer'))
        ->once();
});
