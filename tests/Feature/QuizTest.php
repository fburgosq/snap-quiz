<?php

declare(strict_types=1);

use App\Models\Quiz;
use App\Models\User;
use App\Services\Claude\Exceptions\ClaudeApiException;
use App\Services\Claude\Exceptions\InvalidImageException;
use App\Services\Claude\ExerciseExtractor;
use App\Services\Quiz\QuizImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('renders the SnapQuiz landing page on GET /', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Welcome')
            ->where('quiz', null));
});

test('rejects missing, non-image, unsupported mime, and oversized files with 422 without calling ExerciseExtractor', function (mixed $file): void {
    $extractor = mock(ExerciseExtractor::class);
    $extractor->shouldNotReceive('extract');

    $response = $this->postJson(route('quiz.store'), [
        'image' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
})->with([
    'missing image' => [null],
    'non-image document' => [fn (): UploadedFile => UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf')],
    'disallowed gif mime' => [fn (): UploadedFile => UploadedFile::fake()->image('animated.gif', 100, 100)],
    'oversized image (> 5120 KB)' => [fn (): UploadedFile => UploadedFile::fake()->image('huge.png', 200, 200)->size(5121)],
]);

test('persists quiz, items, blanks, options, token usage, and source image on valid upload and does not leak internal fields', function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();

    $fixtureContents = (string) file_get_contents(base_path('tests/Fixtures/claude-response.json'));

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg_persist_01',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-4-5',
            'stop_reason' => 'end_turn',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $fixtureContents,
                ],
            ],
            'usage' => [
                'input_tokens' => 512,
                'output_tokens' => 184,
            ],
        ]),
    ]);

    $uploadedImage = UploadedFile::fake()->image('client-unsafe-name.png', 300, 200);

    $response = $this->postJson(route('quiz.store'), [
        'image' => $uploadedImage,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'English Grammar & World Geography')
        ->assertJsonPath('data.duplicate', false)
        ->assertJsonCount(3, 'data.items')
        ->assertJsonMissingPath('data.raw_response')
        ->assertJsonMissingPath('data.source_image_hash')
        ->assertJsonMissingPath('data.source_image_path');

    $this->assertDatabaseCount('quizzes', 1);
    $this->assertDatabaseCount('quiz_items', 3);
    $this->assertDatabaseCount('quiz_blanks', 4);
    $this->assertDatabaseCount('quiz_options', 21);

    $quiz = Quiz::query()->firstOrFail();

    expect($quiz->user_id)->toBeNull()
        ->and($quiz->model)->toBe('claude-haiku-4-5')
        ->and($quiz->input_tokens)->toBe(512)
        ->and($quiz->output_tokens)->toBe(184)
        ->and($quiz->source_image_path)->not->toBeNull()
        ->and($quiz->source_image_path)->not->toContain('client-unsafe-name')
        ->and($quiz->source_image_path)->toStartWith('quizzes/'.now()->format('Y/m').'/');

    Storage::disk('local')->assertExists((string) $quiz->source_image_path);
});

test('associates the saved quiz with the authenticated user and flashes quiz on web submission', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $uploadedImage = UploadedFile::fake()->image('worksheet.png', 300, 200);

    $expectedQuiz = [
        'title' => 'World Capitals',
        'exercise' => [
            [
                'sentence' => 'The capital of France is ____.',
                'correct' => 'Paris',
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
                'reason' => 'Paris is the capital of France.',
            ],
        ],
    ];

    $this->mock(ExerciseExtractor::class, function (MockInterface $mock) use ($uploadedImage, $expectedQuiz): void {
        $mock->shouldReceive('extract')
            ->once()
            ->withArgs(fn (mixed $arg): bool => $arg instanceof UploadedFile
                && $arg->getClientOriginalName() === $uploadedImage->getClientOriginalName())
            ->andReturn($expectedQuiz);

        $mock->shouldReceive('getLastUsage')
            ->once()
            ->andReturn([
                'model' => 'claude-haiku-4-5',
                'input_tokens' => 300,
                'output_tokens' => 90,
            ]);
    });

    $response = $this->actingAs($user)
        ->from(route('home'))
        ->post(route('quiz.store'), [
            'image' => $uploadedImage,
        ]);

    $response->assertRedirect(route('home'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('quiz', $expectedQuiz)
        ->assertSessionHas('duplicate', false);

    $quiz = Quiz::query()->firstOrFail();
    expect($quiz->user_id)->toBe($user->id);
});

test('returns existing quiz with duplicate flag and calls ExerciseExtractor zero times on duplicate upload', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $uploadedImage = UploadedFile::fake()->image('worksheet.png', 200, 200);
    $imageHash = (string) hash_file('sha256', (string) $uploadedImage->getRealPath());

    $existingQuiz = Quiz::factory()->withItems(2)->create([
        'user_id' => $user->id,
        'title' => 'Cached Quiz',
        'source_image_hash' => $imageHash,
    ]);

    $extractor = mock(ExerciseExtractor::class);
    $extractor->shouldNotReceive('extract');

    $response = $this->actingAs($user)
        ->postJson(route('quiz.store'), [
            'image' => $uploadedImage,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.uuid', $existingQuiz->uuid)
        ->assertJsonPath('data.title', 'Cached Quiz')
        ->assertJsonPath('data.duplicate', true)
        ->assertJsonCount(2, 'data.items');

    $this->assertDatabaseCount('quizzes', 1);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('returns 502 on AI provider failure, logs error, creates no quiz row, and stores no image', function (): void {
    Storage::fake('local');
    Log::spy();

    $uploadedImage = UploadedFile::fake()->image('worksheet.jpg', 200, 200);

    $this->mock(ExerciseExtractor::class, function (MockInterface $mock): void {
        $mock->shouldReceive('extract')
            ->once()
            ->andThrow(new ClaudeApiException('Rate limit exceeded.', 429, 'rate_limit_error', 'req_123'));
    });

    $response = $this->postJson(route('quiz.store'), [
        'image' => $uploadedImage,
    ]);

    $response->assertStatus(502)
        ->assertJsonPath('message', 'We could not extract a quiz from this image. Please try another image.');

    $this->assertDatabaseCount('quizzes', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Failed to extract or persist quiz from uploaded image.'
            && $context['exception'] === 'Rate limit exceeded.');
});

test('returns 422 when extractor throws InvalidImageException', function (): void {
    Storage::fake('local');

    $uploadedImage = UploadedFile::fake()->image('corrupt.png', 100, 100);

    $this->mock(ExerciseExtractor::class, function (MockInterface $mock): void {
        $mock->shouldReceive('extract')
            ->once()
            ->andThrow(new InvalidImageException('Unsupported image MIME type.'));
    });

    $response = $this->postJson(route('quiz.store'), [
        'image' => $uploadedImage,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    $this->assertDatabaseCount('quizzes', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('deletes the stored image if database import fails after image storage', function (): void {
    Storage::fake('local');

    $uploadedImage = UploadedFile::fake()->image('worksheet.png', 200, 200);

    $this->mock(ExerciseExtractor::class, function (MockInterface $mock): void {
        $mock->shouldReceive('extract')
            ->once()
            ->andReturn([
                'title' => 'World Capitals',
                'exercise' => [],
            ]);

        $mock->shouldReceive('getLastUsage')
            ->once()
            ->andReturn([
                'model' => 'claude-haiku-4-5',
                'input_tokens' => 100,
                'output_tokens' => 20,
            ]);
    });

    $this->mock(QuizImporter::class, function (MockInterface $mock): void {
        $mock->shouldReceive('import')
            ->once()
            ->andThrow(new RuntimeException('Simulated database failure.'));
    });

    $response = $this->postJson(route('quiz.store'), [
        'image' => $uploadedImage,
    ]);

    $response->assertStatus(502);
    $this->assertDatabaseCount('quizzes', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('renders the quiz player page on GET /quiz/{quiz} with eager-loaded items, blanks, and options', function (): void {
    $quiz = Quiz::factory()->withItems(2)->create([
        'title' => 'Playable Quiz',
    ]);

    $response = $this->get(route('quiz.show', $quiz));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('quiz/Show')
            ->where('quiz.uuid', $quiz->uuid)
            ->where('quiz.title', 'Playable Quiz')
            ->has('quiz.items', 2)
            ->has('quiz.items.0.blanks.0.options', 5));
});
