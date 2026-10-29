<?php

declare(strict_types=1);

use App\Models\Quiz;
use App\Models\User;
use App\Services\Quiz\QuizImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * @return array<string, mixed>
 */
function loadClaudeFixture(): array
{
    $contents = file_get_contents(base_path('tests/Fixtures/claude-response.json'));

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

test('migrations run up and down without errors', function (): void {
    expect(Schema::hasTable('quizzes'))->toBeTrue()
        ->and(Schema::hasTable('quiz_items'))->toBeTrue()
        ->and(Schema::hasTable('quiz_blanks'))->toBeTrue()
        ->and(Schema::hasTable('quiz_options'))->toBeTrue();

    $exitCode = Artisan::call('migrate:rollback', ['--step' => 4, '--force' => true]);
    expect($exitCode)->toBe(0)
        ->and(Schema::hasTable('quizzes'))->toBeFalse()
        ->and(Schema::hasTable('quiz_items'))->toBeFalse()
        ->and(Schema::hasTable('quiz_blanks'))->toBeFalse()
        ->and(Schema::hasTable('quiz_options'))->toBeFalse();

    $remigrateCode = Artisan::call('migrate', ['--force' => true]);
    expect($remigrateCode)->toBe(0)
        ->and(Schema::hasTable('quizzes'))->toBeTrue()
        ->and(Schema::hasTable('quiz_options'))->toBeTrue();
});

test('imports a single-blank exercise with a single correct answer and 4 incorrect options', function (): void {
    $payload = [
        'title' => 'World Capitals',
        'exercise' => [
            [
                'sentence' => 'The capital of France is ____.',
                'correct' => 'Paris',
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
                'reason' => 'Paris is the capital and largest city of France.',
            ],
        ],
    ];

    $user = User::factory()->create();
    $hash = hash('sha256', 'sample-image-bytes');

    $quiz = app(QuizImporter::class)->import($payload, $user->id, [
        'source_image_path' => 'uploads/worksheet.png',
        'source_image_hash' => $hash,
        'model' => 'claude-haiku-4-5',
        'input_tokens' => 410,
        'output_tokens' => 98,
    ]);

    expect($quiz->uuid)->toBeUuid()
        ->and($quiz->getRouteKeyName())->toBe('uuid')
        ->and($quiz->user_id)->toBe($user->id)
        ->and($quiz->title)->toBe('World Capitals')
        ->and($quiz->source_image_hash)->toBe($hash)
        ->and($quiz->model)->toBe('claude-haiku-4-5')
        ->and($quiz->input_tokens)->toBe(410)
        ->and($quiz->output_tokens)->toBe(98)
        ->and($quiz->raw_response)->toBe($payload);

    expect($quiz->items)->toHaveCount(1);

    $item = $quiz->items->firstOrFail();
    expect($item->position)->toBe(1)
        ->and($item->blank_count)->toBe(1)
        ->and($item->sentence)->toBe('The capital of France is ____.')
        ->and($item->reason)->toBe('Paris is the capital and largest city of France.');

    expect($item->blanks)->toHaveCount(1);

    $blank = $item->blanks->firstOrFail();
    expect($blank->position)->toBe(1)
        ->and($blank->correctOptions->pluck('text')->all())->toBe(['Paris'])
        ->and($blank->incorrectOptions->pluck('text')->all())->toBe(['Lyon', 'Marseille', 'Toulouse', 'Nice']);
});

test('imports a single-blank exercise with multiple valid correct answers', function (): void {
    $payload = [
        'title' => 'Spelling Variants',
        'exercise' => [
            [
                'sentence' => 'My favorite ____ is blue.',
                'correct' => ['color', 'colour'],
                'incorrect' => ['flavor', 'sound', 'weight', 'height'],
                'reason' => 'Both US and UK spellings are valid.',
            ],
        ],
    ];

    $quiz = app(QuizImporter::class)->import($payload);
    $blank = $quiz->items->firstOrFail()->blanks->firstOrFail();

    expect($blank->correctOptions->pluck('text')->all())->toBe(['color', 'colour'])
        ->and($blank->incorrectOptions->pluck('text')->all())->toBe(['flavor', 'sound', 'weight', 'height']);
});

test('imports a two-blank exercise with 2 correct answers and 8 incorrect options split across 2 blanks', function (): void {
    $payload = [
        'title' => 'Present Simple',
        'exercise' => [
            [
                'sentence' => 'She ____ to school every morning and ____ her friends at the gate.',
                'correct' => ['walks', 'meets'],
                'incorrect' => ['walk', 'walking', 'walked', 'to walk', 'meet', 'meeting', 'met', 'to meet'],
                'reason' => 'Third-person singular verbs take -s.',
            ],
        ],
    ];

    $quiz = app(QuizImporter::class)->import($payload);
    $item = $quiz->items->firstOrFail();

    expect($item->blank_count)->toBe(2)
        ->and($item->blanks)->toHaveCount(2);

    [$firstBlank, $secondBlank] = $item->blanks;

    expect($firstBlank->position)->toBe(1)
        ->and($firstBlank->correctOptions->pluck('text')->all())->toBe(['walks'])
        ->and($firstBlank->incorrectOptions->pluck('text')->all())->toBe(['walk', 'walking', 'walked', 'to walk']);

    expect($secondBlank->position)->toBe(2)
        ->and($secondBlank->correctOptions->pluck('text')->all())->toBe(['meets'])
        ->and($secondBlank->incorrectOptions->pluck('text')->all())->toBe(['meet', 'meeting', 'met', 'to meet']);
});

test('logs warnings and does not throw when correct or incorrect counts mismatch blank_count', function (): void {
    Log::spy();

    $payload = [
        'title' => 'Mismatch Cases',
        'exercise' => [
            [
                'sentence' => 'First ____ and second ____ blank.',
                'correct' => ['one', 'two', 'three'],
                'incorrect' => ['wrong1', 'wrong2', 'wrong3'],
                'reason' => 'Testing mismatch resilience.',
            ],
        ],
    ];

    $quiz = app(QuizImporter::class)->import($payload);
    $blanks = $quiz->items->firstOrFail()->blanks;

    expect($blanks)->toHaveCount(2)
        ->and($blanks[0]->correctOptions->pluck('text')->all())->toBe(['one', 'two', 'three'])
        ->and($blanks[0]->incorrectOptions->pluck('text')->all())->toBe(['wrong1', 'wrong2', 'wrong3'])
        ->and($blanks[1]->correctOptions)->toBeEmpty()
        ->and($blanks[1]->incorrectOptions)->toBeEmpty();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'correct answer count does not match'))
        ->once();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'incorrect option count does not match'))
        ->once();
});

test('rolls back the entire import transaction when a later exercise item fails validation', function (): void {
    $payloadWithInvalidSecondItem = [
        'title' => 'Atomic Rollback Test',
        'exercise' => [
            [
                'sentence' => 'The capital of France is ____.',
                'correct' => 'Paris',
                'incorrect' => ['Lyon', 'Marseille', 'Toulouse', 'Nice'],
                'reason' => 'Valid first item.',
            ],
            [
                'sentence' => 'Broken second item without reason or correct.',
            ],
        ],
    ];

    expect(fn (): Quiz => app(QuizImporter::class)->import($payloadWithInvalidSecondItem))
        ->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('quizzes', 0);
    $this->assertDatabaseCount('quiz_items', 0);
    $this->assertDatabaseCount('quiz_blanks', 0);
    $this->assertDatabaseCount('quiz_options', 0);
});

test('round-trips the fixture JSON losslessly via toExerciseArray', function (): void {
    $fixture = loadClaudeFixture();

    $quiz = app(QuizImporter::class)->import($fixture);

    expect($quiz->toExerciseArray())->toBe($fixture);
});

test('finds a duplicate quiz by image hash and user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $hash = hash('sha256', 'duplicate-worksheet');

    $quiz = Quiz::factory()->withItems(2)->create([
        'user_id' => $user->id,
        'source_image_hash' => $hash,
    ]);

    expect(QuizImporter::findDuplicate($hash, $user->id)?->id)->toBe($quiz->id)
        ->and(QuizImporter::findDuplicate($hash, $otherUser->id))->toBeNull()
        ->and(QuizImporter::findDuplicate($hash, null))->toBeNull();
});

test('keeps child rows on soft delete and cascades deletion on forceDelete', function (): void {
    $quiz = Quiz::factory()->withItems(2)->create();

    $this->assertDatabaseCount('quizzes', 1);
    $this->assertDatabaseCount('quiz_items', 2);
    $this->assertDatabaseCount('quiz_blanks', 2);
    $this->assertDatabaseCount('quiz_options', 10);

    $quiz->delete();

    $this->assertSoftDeleted('quizzes', ['id' => $quiz->id]);
    $this->assertDatabaseCount('quiz_items', 2);
    $this->assertDatabaseCount('quiz_blanks', 2);
    $this->assertDatabaseCount('quiz_options', 10);

    $quiz->forceDelete();

    $this->assertDatabaseCount('quizzes', 0);
    $this->assertDatabaseCount('quiz_items', 0);
    $this->assertDatabaseCount('quiz_blanks', 0);
    $this->assertDatabaseCount('quiz_options', 0);
});

test('skips duplicate option texts case-insensitively and never creates an incorrect option equal to a correct one', function (): void {
    $payload = [
        'title' => 'Deduplication Test',
        'exercise' => [
            [
                'sentence' => 'Water freezes at ____ degrees Celsius.',
                'correct' => ['zero', ' ZERO '],
                'incorrect' => ['Zero', 'ten', 'TEN ', 'twenty'],
                'reason' => 'Water freezes at 0 degrees Celsius.',
            ],
        ],
    ];

    $quiz = app(QuizImporter::class)->import($payload);
    $blank = $quiz->items->firstOrFail()->blanks->firstOrFail();

    expect($blank->correctOptions->pluck('text')->all())->toBe(['zero'])
        ->and($blank->incorrectOptions->pluck('text')->all())->toBe(['ten', 'twenty']);
});
