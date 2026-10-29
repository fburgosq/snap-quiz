<?php

declare(strict_types=1);

namespace App\Services\Claude;

use App\Services\Claude\Exceptions\ClaudeApiException;
use App\Services\Claude\Exceptions\ClaudeException;
use App\Services\Claude\Exceptions\InvalidClaudeResponseException;
use App\Services\Claude\Exceptions\InvalidImageException;
use finfo;
use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Extracts structured fill-in-the-blank exercises from an image using Anthropic Claude or OpenAI ChatGPT.
 *
 * Usage example:
 * ```php
 * $extractor = app(ExerciseExtractor::class);
 * $result = $extractor->extract($request->file('worksheet'));
 * // ['title' => 'World Capitals', 'exercise' => [...]]
 * ```
 */
class ExerciseExtractor
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private const array RETRYABLE_STATUS_CODES = [
        429,
        500,
        502,
        503,
        529,
    ];

    private const int CONNECT_TIMEOUT_SECONDS = 10;

    private const int MAX_ATTEMPTS = 3;

    private const string USER_INSTRUCTION = 'Analyze the attached image and return the JSON object exactly as specified.';

    private readonly AiProvider $provider;

    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly string $version;

    private readonly string $model;

    private readonly int $maxTokens;

    private readonly int $timeout;

    private readonly int $maxImageBytes;

    private readonly ?int $resizeMaxEdge;

    private readonly string $promptPath;

    private ?string $cachedPrompt = null;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $version = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?int $timeout = null,
        ?int $maxImageBytes = null,
        ?int $resizeMaxEdge = null,
        ?string $promptPath = null,
        AiProvider|string|null $provider = null,
    ) {
        $this->provider = $provider instanceof AiProvider
            ? $provider
            : AiProvider::fromConfig($provider);

        /** @var array<string, mixed> $config */
        $config = (array) config("services.{$this->provider->configKey()}", []);

        $this->apiKey = $apiKey ?? (string) ($config['key'] ?? '');
        $this->baseUrl = $baseUrl ?? (string) ($config['base_url'] ?? $this->provider->defaultBaseUrl());
        $this->version = $version ?? (string) ($config['version'] ?? '2023-06-01');
        $this->model = $model ?? (string) ($config['model'] ?? $this->provider->defaultModel());
        $this->maxTokens = $maxTokens ?? (int) ($config['max_tokens'] ?? 8192);
        $this->timeout = $timeout ?? (int) ($config['timeout'] ?? 120);
        $this->maxImageBytes = $maxImageBytes ?? (int) ($config['max_image_bytes'] ?? 5 * 1024 * 1024);

        $configuredResize = $config['resize_max_edge'] ?? null;
        $this->resizeMaxEdge = $resizeMaxEdge ?? ($configuredResize !== null ? (int) $configuredResize : null);

        $this->promptPath = $promptPath ?? resource_path('prompts/fill-in-the-blank-extractor.txt');
    }

    /**
     * Extract fill-in-the-blank exercises from an uploaded image or local file path.
     *
     * @return array{
     *     title: string,
     *     exercise: list<array{
     *         sentence: string,
     *         correct: string|list<string>,
     *         incorrect: list<string>,
     *         reason: string
     *     }>
     * }
     *
     * @throws ClaudeApiException
     * @throws InvalidImageException
     * @throws InvalidClaudeResponseException
     * @throws ClaudeException
     */
    public function extract(UploadedFile|string $image): array
    {
        $prompt = $this->loadPrompt();
        $preparedImage = $this->prepareImage($image);
        $payload = $this->buildPayload($prompt, $preparedImage);

        $result = $this->send($payload);

        try {
            $decoded = $this->parse($result['text']);
        } catch (InvalidClaudeResponseException) {
            $result = $this->send($payload);
            $decoded = $this->parse($result['text']);
        }

        $validated = $this->validate($decoded);

        Log::info('Claude exercise extraction completed.', [
            'provider' => $this->provider->value,
            'model' => $result['model'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'duration_ms' => $result['duration_ms'],
            'request_id' => $result['request_id'],
            'exercises_count' => count($validated['exercise']),
        ]);

        return $validated;
    }

    /**
     * Send a simple text prompt to the configured AI provider to verify API connectivity and credentials.
     *
     * @throws ClaudeApiException
     * @throws InvalidClaudeResponseException
     */
    public function ping(string $prompt = 'Reply with "OK" to confirm the API connection is working.'): string
    {
        $result = $this->send($this->buildPingPayload($prompt));

        return $result['text'];
    }

    private function loadPrompt(): string
    {
        if ($this->cachedPrompt !== null) {
            return $this->cachedPrompt;
        }

        if (! is_file($this->promptPath) || ! is_readable($this->promptPath)) {
            throw new ClaudeException("System prompt file is missing or unreadable at [{$this->promptPath}].");
        }

        $contents = @file_get_contents($this->promptPath);

        if ($contents === false || trim($contents) === '') {
            throw new ClaudeException("System prompt file is empty at [{$this->promptPath}].");
        }

        return $this->cachedPrompt = $contents;
    }

    /**
     * Validate, optionally resize, and base64-encode the input image.
     *
     * @return array{media_type: string, data: string}
     */
    private function prepareImage(UploadedFile|string $image): array
    {
        $path = $image instanceof UploadedFile
            ? ($image->getRealPath() ?: $image->path())
            : $image;

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new InvalidImageException('The image file does not exist or is not readable.');
        }

        $bytes = @file_get_contents($path);

        if ($bytes === false || $bytes === '') {
            throw new InvalidImageException('The image file is empty or could not be read.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($bytes);

        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $detected = is_string($mimeType) ? $mimeType : 'unknown';

            throw new InvalidImageException("Unsupported image MIME type [{$detected}]. Allowed types: ".implode(', ', self::ALLOWED_MIME_TYPES).'.');
        }

        $bytes = $this->resizeIfNeeded($bytes, $mimeType);

        if (strlen($bytes) > $this->maxImageBytes) {
            throw new InvalidImageException("Image size exceeds the maximum allowed size of {$this->maxImageBytes} bytes.");
        }

        return [
            'media_type' => $mimeType,
            'data' => base64_encode($bytes),
        ];
    }

    private function resizeIfNeeded(string $bytes, string $mimeType): string
    {
        if ($this->resizeMaxEdge === null || $this->resizeMaxEdge <= 0 || $mimeType === 'image/gif') {
            return $bytes;
        }

        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            return $bytes;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $longestEdge = max($width, $height);

        if ($longestEdge <= $this->resizeMaxEdge) {
            return $bytes;
        }

        $scale = $this->resizeMaxEdge / $longestEdge;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $destination = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $destination instanceof GdImage) {
            return $bytes;
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }

        imagecopyresampled($destination, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = match ($mimeType) {
            'image/jpeg' => function_exists('imagejpeg') ? imagejpeg($destination, null, 85) : false,
            'image/png' => function_exists('imagepng') ? imagepng($destination) : false,
            'image/webp' => function_exists('imagewebp') ? imagewebp($destination, null, 85) : false,
            default => false,
        };
        $resizedBytes = ob_get_clean();

        if (! $encoded || $resizedBytes === false || $resizedBytes === '') {
            return $bytes;
        }

        return $resizedBytes;
    }

    /**
     * Build the request payload for the configured AI provider.
     *
     * @param  array{media_type: string, data: string}  $image
     * @return array<string, mixed>
     */
    private function buildPayload(string $prompt, array $image): array
    {
        if ($this->provider === AiProvider::OpenAi) {
            return [
                'model' => $this->model,
                'max_completion_tokens' => $this->maxTokens,
                'response_format' => [
                    'type' => 'json_object',
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $prompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$image['media_type']};base64,{$image['data']}",
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => self::USER_INSTRUCTION,
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $prompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $image['media_type'],
                                'data' => $image['data'],
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => self::USER_INSTRUCTION,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a simple text-only payload for connectivity checks.
     *
     * @return array<string, mixed>
     */
    private function buildPingPayload(string $prompt): array
    {
        if ($this->provider === AiProvider::OpenAi) {
            return [
                'model' => $this->model,
                'max_completion_tokens' => 256,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ];
        }

        return [
            'model' => $this->model,
            'max_tokens' => 256,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];
    }

    /**
     * Send the request to the configured AI provider and extract response metadata.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     text: string,
     *     model: string,
     *     input_tokens: ?int,
     *     output_tokens: ?int,
     *     duration_ms: int,
     *     request_id: ?string
     * }
     */
    private function send(array $payload): array
    {
        $startTime = hrtime(true);

        $headers = $this->provider === AiProvider::OpenAi
            ? ['Authorization' => "Bearer {$this->apiKey}"]
            : [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->version,
            ];

        try {
            /** @var Response $response */
            $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->withHeaders($headers)
                ->asJson()
                ->acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout($this->timeout)
                ->retry(
                    self::MAX_ATTEMPTS,
                    fn (int $attempt, Throwable $exception): int => $this->retryDelayMs($attempt, $exception),
                    fn (Throwable $exception): bool => $this->shouldRetry($exception),
                    throw: false,
                )
                ->post($this->provider->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw new ClaudeApiException(
                message: "Failed to connect to the {$this->provider->value} API.",
                statusCode: 0,
                errorType: 'connection_error',
                requestId: null,
                previous: $exception,
            );
        }

        $durationMs = (int) round((hrtime(true) - $startTime) / 1_000_000);
        $requestIdHeader = $response->header('request-id') !== ''
            ? $response->header('request-id')
            : $response->header('x-request-id');
        $requestId = $requestIdHeader !== '' ? $requestIdHeader : null;

        if (! $response->successful()) {
            /** @var array<string, mixed> $errorBody */
            $errorBody = is_array($response->json()) ? $response->json() : [];
            $errorType = data_get($errorBody, 'error.type') ?? data_get($errorBody, 'error.code');
            $errorMessage = data_get($errorBody, 'error.message');

            throw new ClaudeApiException(
                message: is_string($errorMessage) && $errorMessage !== ''
                    ? $errorMessage
                    : "API request failed with HTTP status {$response->status()}.",
                statusCode: $response->status(),
                errorType: is_string($errorType) && $errorType !== '' ? $errorType : null,
                requestId: $requestId,
            );
        }

        /** @var array<string, mixed> $body */
        $body = is_array($response->json()) ? $response->json() : [];

        return $this->provider === AiProvider::OpenAi
            ? $this->extractOpenAiResponse($body, $durationMs, $requestId)
            : $this->extractAnthropicResponse($body, $durationMs, $requestId);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{
     *     text: string,
     *     model: string,
     *     input_tokens: ?int,
     *     output_tokens: ?int,
     *     duration_ms: int,
     *     request_id: ?string
     * }
     */
    private function extractAnthropicResponse(array $body, int $durationMs, ?string $requestId): array
    {
        if (($body['stop_reason'] ?? null) === 'max_tokens') {
            throw new InvalidClaudeResponseException(
                'Claude response was truncated because stop_reason is "max_tokens". Consider increasing ANTHROPIC_MAX_TOKENS.'
            );
        }

        $text = '';
        $contentBlocks = $body['content'] ?? [];

        if (is_array($contentBlocks)) {
            foreach ($contentBlocks as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                    $text .= $block['text'];
                }
            }
        }

        $inputTokens = data_get($body, 'usage.input_tokens');
        $outputTokens = data_get($body, 'usage.output_tokens');
        $responseModel = $body['model'] ?? null;

        return [
            'text' => $text,
            'model' => is_string($responseModel) && $responseModel !== '' ? $responseModel : $this->model,
            'input_tokens' => is_int($inputTokens) ? $inputTokens : null,
            'output_tokens' => is_int($outputTokens) ? $outputTokens : null,
            'duration_ms' => $durationMs,
            'request_id' => $requestId,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{
     *     text: string,
     *     model: string,
     *     input_tokens: ?int,
     *     output_tokens: ?int,
     *     duration_ms: int,
     *     request_id: ?string
     * }
     */
    private function extractOpenAiResponse(array $body, int $durationMs, ?string $requestId): array
    {
        $finishReason = data_get($body, 'choices.0.finish_reason');

        if ($finishReason === 'length') {
            throw new InvalidClaudeResponseException(
                'OpenAI response was truncated because finish_reason is "length". Consider increasing OPENAI_MAX_TOKENS.'
            );
        }

        $messageContent = data_get($body, 'choices.0.message.content');
        $text = '';

        if (is_string($messageContent)) {
            $text = $messageContent;
        } elseif (is_array($messageContent)) {
            foreach ($messageContent as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }

        $inputTokens = data_get($body, 'usage.prompt_tokens');
        $outputTokens = data_get($body, 'usage.completion_tokens');
        $responseModel = $body['model'] ?? null;

        return [
            'text' => $text,
            'model' => is_string($responseModel) && $responseModel !== '' ? $responseModel : $this->model,
            'input_tokens' => is_int($inputTokens) ? $inputTokens : null,
            'output_tokens' => is_int($outputTokens) ? $outputTokens : null,
            'duration_ms' => $durationMs,
            'request_id' => $requestId,
        ];
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            return in_array($exception->response->status(), self::RETRYABLE_STATUS_CODES, true);
        }

        return false;
    }

    private function retryDelayMs(int $attempt, Throwable $exception): int
    {
        if ($exception instanceof RequestException) {
            $retryAfter = trim($exception->response->header('retry-after'));

            if ($retryAfter !== '') {
                if (is_numeric($retryAfter)) {
                    return max(0, (int) round(((float) $retryAfter) * 1000));
                }

                $timestamp = strtotime($retryAfter);

                if ($timestamp !== false) {
                    return max(0, ($timestamp - time()) * 1000);
                }
            }
        }

        return match ($attempt) {
            1 => 1000,
            default => 3000,
        };
    }

    /**
     * Parse the raw text response into a decoded JSON associative array.
     *
     * @return array<string, mixed>
     */
    private function parse(string $text): array
    {
        $cleaned = trim($text);

        if (str_starts_with($cleaned, '```')) {
            $cleaned = (string) preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
            $cleaned = (string) preg_replace('/\s*```$/', '', $cleaned);
            $cleaned = trim($cleaned);
        }

        try {
            $decoded = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidClaudeResponseException(
                'Failed to decode JSON from Claude response: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new InvalidClaudeResponseException('Claude response JSON must decode to an object.');
        }

        return $decoded;
    }

    /**
     * Validate the decoded payload structure and emit soft warnings when appropriate.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     title: string,
     *     exercise: list<array{
     *         sentence: string,
     *         correct: string|list<string>,
     *         incorrect: list<string>,
     *         reason: string
     *     }>
     * }
     */
    private function validate(array $data): array
    {
        if (! isset($data['title']) || ! is_string($data['title']) || trim($data['title']) === '') {
            throw new InvalidClaudeResponseException('Claude response is missing a non-empty string "title".');
        }

        if (! isset($data['exercise']) || ! is_array($data['exercise'])) {
            throw new InvalidClaudeResponseException('Claude response is missing an "exercise" array.');
        }

        $validatedExercises = [];

        foreach ($data['exercise'] as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidClaudeResponseException("Exercise at index {$index} must be an object.");
            }

            if (! isset($item['sentence']) || ! is_string($item['sentence'])) {
                throw new InvalidClaudeResponseException("Exercise at index {$index} is missing a string \"sentence\".");
            }

            $correct = $this->validateCorrectField($item['correct'] ?? null, (int) $index);
            $incorrect = $this->validateIncorrectField($item['incorrect'] ?? null, (int) $index);

            if (! isset($item['reason']) || ! is_string($item['reason']) || trim($item['reason']) === '') {
                throw new InvalidClaudeResponseException("Exercise at index {$index} is missing a non-empty string \"reason\".");
            }

            $this->logSoftWarnings($item['sentence'], $correct, $incorrect, (int) $index);

            $validatedExercises[] = [
                'sentence' => $item['sentence'],
                'correct' => $correct,
                'incorrect' => $incorrect,
                'reason' => $item['reason'],
            ];
        }

        return [
            'title' => $data['title'],
            'exercise' => $validatedExercises,
        ];
    }

    /**
     * @return string|list<string>
     */
    private function validateCorrectField(mixed $correct, int $index): string|array
    {
        if (is_string($correct) && trim($correct) !== '') {
            return $correct;
        }

        if (is_array($correct) && $correct !== []) {
            $normalized = [];

            foreach ($correct as $value) {
                if (! is_string($value) || trim($value) === '') {
                    throw new InvalidClaudeResponseException(
                        "Exercise at index {$index} has an invalid \"correct\" array; all items must be non-empty strings."
                    );
                }

                $normalized[] = $value;
            }

            return $normalized;
        }

        throw new InvalidClaudeResponseException(
            "Exercise at index {$index} must have a non-empty string or non-empty array of strings for \"correct\"."
        );
    }

    /**
     * @return list<string>
     */
    private function validateIncorrectField(mixed $incorrect, int $index): array
    {
        if (! is_array($incorrect)) {
            throw new InvalidClaudeResponseException("Exercise at index {$index} is missing an \"incorrect\" array.");
        }

        $normalized = [];

        foreach ($incorrect as $value) {
            if (! is_string($value)) {
                throw new InvalidClaudeResponseException(
                    "Exercise at index {$index} has a non-string value in \"incorrect\"."
                );
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @param  string|list<string>  $correct
     * @param  list<string>  $incorrect
     */
    private function logSoftWarnings(string $sentence, string|array $correct, array $incorrect, int $index): void
    {
        $blankCount = substr_count($sentence, '____');

        if ($blankCount === 0) {
            Log::warning('Claude exercise sentence contains no "____" blanks.', [
                'exercise_index' => $index,
            ]);
        }

        if (count($incorrect) !== 4 * $blankCount) {
            Log::warning('Claude exercise "incorrect" count does not match 4x the number of blanks.', [
                'exercise_index' => $index,
                'blank_count' => $blankCount,
                'incorrect_count' => count($incorrect),
            ]);
        }

        $correctList = is_array($correct) ? $correct : [$correct];

        foreach ($incorrect as $option) {
            if (in_array($option, $correctList, true)) {
                Log::warning('Claude exercise contains an "incorrect" option that equals a correct answer.', [
                    'exercise_index' => $index,
                    'duplicate_option' => $option,
                ]);

                break;
            }
        }
    }
}
