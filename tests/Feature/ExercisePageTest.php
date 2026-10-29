<?php

declare(strict_types=1);

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('renders the exercises page ordered newest first with items_count and excludes soft-deleted quizzes', function (): void {
    $olderQuiz = Quiz::factory()->withItems(2)->create([
        'title' => 'Older Grammar Exercise',
        'created_at' => now()->subDays(2),
    ]);

    $newerQuiz = Quiz::factory()->withItems(4)->create([
        'title' => 'Newer Vocabulary Exercise',
        'created_at' => now()->subDay(),
    ]);

    $deletedQuiz = Quiz::factory()->withItems(1)->create([
        'title' => 'Soft Deleted Exercise',
        'created_at' => now(),
    ]);
    $deletedQuiz->delete();

    $response = $this->get(route('exercises.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('exercises/Index')
            ->has('quizzes.data', 2)
            ->where('quizzes.meta.total', 2)
            ->where('quizzes.data.0.uuid', $newerQuiz->uuid)
            ->where('quizzes.data.0.title', 'Newer Vocabulary Exercise')
            ->where('quizzes.data.0.items_count', 4)
            ->where('quizzes.data.1.uuid', $olderQuiz->uuid)
            ->where('quizzes.data.1.title', 'Older Grammar Exercise')
            ->where('quizzes.data.1.items_count', 2));
});

test('paginates exercises at 24 per page and preserves query string parameters', function (): void {
    Quiz::factory()->count(30)->create([
        'created_at' => now()->subMinutes(10),
    ]);

    $response = $this->get(route('exercises.index', ['filter' => 'recent', 'page' => 1]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('exercises/Index')
            ->has('quizzes.data', 24)
            ->where('quizzes.meta.per_page', 24)
            ->where('quizzes.meta.total', 30)
            ->where('quizzes.meta.last_page', 2)
            ->where('quizzes.links.next', fn (string $url): bool => str_contains($url, 'filter=recent') && str_contains($url, 'page=2')));
});

test('redirects out-of-range page numbers to the last available page', function (): void {
    Quiz::factory()->count(5)->create();

    $response = $this->get(route('exercises.index', ['page' => 99]));

    $response->assertRedirect(route('exercises.index', ['page' => 1]));
});

test('shows only the current authenticated user quizzes when authenticated', function (): void {
    $currentUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownQuiz = Quiz::factory()->create([
        'user_id' => $currentUser->id,
        'title' => 'My Personal Quiz',
    ]);

    Quiz::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Someone Else Quiz',
    ]);

    Quiz::factory()->create([
        'user_id' => null,
        'title' => 'Guest Quiz',
    ]);

    $response = $this->actingAs($currentUser)->get(route('exercises.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('exercises/Index')
            ->has('quizzes.data', 1)
            ->where('quizzes.data.0.uuid', $ownQuiz->uuid)
            ->where('quizzes.data.0.title', 'My Personal Quiz'));
});

test('exposes has_image and image_url on the exercises page without leaking sensitive or internal fields', function (): void {
    $quizWithImage = Quiz::factory()->withItems(1)->create([
        'title' => 'Safe Public Summary Quiz',
        'source_image_path' => 'quizzes/2026/10/secret.png',
        'source_image_hash' => str_repeat('a', 64),
        'model' => 'claude-haiku-4-5',
        'input_tokens' => 500,
        'output_tokens' => 200,
        'raw_response' => ['title' => 'Secret Raw JSON', 'exercise' => []],
        'created_at' => now(),
    ]);

    $quizWithoutImage = Quiz::factory()->withItems(1)->create([
        'title' => 'Quiz Without Image',
        'source_image_path' => null,
        'created_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('exercises.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('exercises/Index')
            ->has('quizzes.data', 2)
            ->has('quizzes.data.0', fn (Assert $item): Assert => $item
                ->hasAll(['uuid', 'title', 'items_count', 'created_at', 'has_image', 'image_url'])
                ->where('has_image', true)
                ->where('image_url', route('exercises.image', $quizWithImage->uuid))
                ->missing('raw_response')
                ->missing('source_image_hash')
                ->missing('source_image_path')
                ->missing('model')
                ->missing('input_tokens')
                ->missing('output_tokens'))
            ->has('quizzes.data.1', fn (Assert $item): Assert => $item
                ->hasAll(['uuid', 'title', 'items_count', 'created_at', 'has_image', 'image_url'])
                ->where('uuid', $quizWithoutImage->uuid)
                ->where('has_image', false)
                ->where('image_url', null)
                ->missing('source_image_path')
                ->missing('source_image_hash')));
});

test('renders the empty state when there are no quizzes', function (): void {
    $response = $this->get(route('exercises.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('exercises/Index')
            ->has('quizzes.data', 0)
            ->where('quizzes.meta.total', 0));
});

test('renders the how-to-add page on GET /how-to-add', function (): void {
    $response = $this->get(route('how-to-add'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('HowToAdd'));
});

test('shares header navigation links for Exercises and How to add across pages', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Exercises')
        ->assertSee('How to add')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('navigation', 2)
            ->where('navigation.0.label', 'Exercises')
            ->where('navigation.0.href', '/exercises')
            ->where('navigation.1.label', 'How to add')
            ->where('navigation.1.href', '/how-to-add'));
});

test('login and register routes are disabled and return 404', function (): void {
    $this->get('/login')->assertNotFound();
    $this->post('/login')->assertNotFound();
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('streams the original quiz image with correct headers for the authorized owner', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $fakeImage = UploadedFile::fake()->image('worksheet.png', 200, 150);
    $storedPath = $fakeImage->storeAs('quizzes/2026/10', 'stored-worksheet.png', ['disk' => 'local']);

    $quiz = Quiz::factory()->create([
        'user_id' => $owner->id,
        'source_image_path' => $storedPath,
    ]);

    $response = $this->actingAs($owner)->get(route('exercises.image', $quiz->uuid));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=3600, private');
});

test('streams the original quiz image for a guest-owned quiz when requested by a guest', function (): void {
    Storage::fake('local');

    $fakeImage = UploadedFile::fake()->image('guest-worksheet.jpg', 200, 150);
    $storedPath = $fakeImage->storeAs('quizzes/2026/10', 'guest-worksheet.jpg', ['disk' => 'local']);

    $quiz = Quiz::factory()->create([
        'user_id' => null,
        'source_image_path' => $storedPath,
    ]);

    $response = $this->get(route('exercises.image', $quiz->uuid));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('denies access to another user or unauthenticated guest for a user-owned quiz image', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $fakeImage = UploadedFile::fake()->image('private.png', 100, 100);
    $storedPath = $fakeImage->storeAs('quizzes/2026/10', 'private.png', ['disk' => 'local']);

    $quiz = Quiz::factory()->create([
        'user_id' => $owner->id,
        'source_image_path' => $storedPath,
    ]);

    $this->actingAs($intruder)
        ->get(route('exercises.image', $quiz->uuid))
        ->assertForbidden();

    $this->post('/logout');
    auth()->forgetGuards();

    $this->get(route('exercises.image', $quiz->uuid))
        ->assertForbidden();
});

test('returns 404 when the quiz has a null image path or the image file is missing on disk', function (): void {
    Storage::fake('local');

    $quizWithoutPath = Quiz::factory()->create([
        'user_id' => null,
        'source_image_path' => null,
    ]);

    $quizWithMissingFile = Quiz::factory()->create([
        'user_id' => null,
        'source_image_path' => 'quizzes/2026/10/does-not-exist.png',
    ]);

    $this->get(route('exercises.image', $quizWithoutPath->uuid))
        ->assertNotFound();

    $this->get(route('exercises.image', $quizWithMissingFile->uuid))
        ->assertNotFound();
});
