<?php

declare(strict_types=1);

use App\Services\Claude\Exceptions\ClaudeApiException;
use App\Services\Claude\ExerciseExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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

test('rejects missing, non-image, unsupported mime, and oversized files without calling ExerciseExtractor', function (mixed $file): void {
    $extractor = mock(ExerciseExtractor::class);
    $extractor->shouldNotReceive('extract');

    $response = $this->from(route('home'))
        ->post(route('quiz.store'), [
            'image' => $file,
        ]);

    $response->assertRedirect(route('home'))
        ->assertSessionHasErrors(['image']);
})->with([
    'missing image' => [null],
    'non-image document' => [fn (): UploadedFile => UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf')],
    'disallowed gif mime' => [fn (): UploadedFile => UploadedFile::fake()->image('animated.gif', 100, 100)],
    'oversized image (> 5120 KB)' => [fn (): UploadedFile => UploadedFile::fake()->image('huge.png', 200, 200)->size(5121)],
]);

test('calls ExerciseExtractor once with the uploaded image and flashes the extracted quiz on valid submission', function (): void {
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
    });

    $response = $this->from(route('home'))
        ->post(route('quiz.store'), [
            'image' => $uploadedImage,
        ]);

    $response->assertRedirect(route('home'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('quiz', $expectedQuiz);
});

test('logs the exception and redirects back with a friendly error when ExerciseExtractor fails', function (): void {
    Log::spy();

    $uploadedImage = UploadedFile::fake()->image('worksheet.jpg', 200, 200);

    $this->mock(ExerciseExtractor::class, function (MockInterface $mock): void {
        $mock->shouldReceive('extract')
            ->once()
            ->andThrow(new ClaudeApiException('Rate limit exceeded.', 429, 'rate_limit_error', 'req_123'));
    });

    $response = $this->from(route('home'))
        ->post(route('quiz.store'), [
            'image' => $uploadedImage,
        ]);

    $response->assertRedirect(route('home'))
        ->assertSessionHasErrors([
            'image' => 'We could not extract a quiz from this image. Please try another image.',
        ]);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Failed to extract quiz from uploaded image.'
            && $context['exception'] === 'Rate limit exceeded.');
});
