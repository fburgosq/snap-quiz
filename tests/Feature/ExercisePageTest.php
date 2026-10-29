<?php

declare(strict_types=1);

use App\Models\Quiz;
use App\Models\User;
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

test('does not expose sensitive or internal fields on the exercises page', function (): void {
    Quiz::factory()->withItems(1)->create([
        'title' => 'Safe Public Summary Quiz',
        'source_image_path' => 'quizzes/2026/10/secret.png',
        'source_image_hash' => str_repeat('a', 64),
        'model' => 'claude-haiku-4-5',
        'input_tokens' => 500,
        'output_tokens' => 200,
        'raw_response' => ['title' => 'Secret Raw JSON', 'exercise' => []],
    ]);

    $response = $this->get(route('exercises.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('exercises/Index')
            ->has('quizzes.data', 1)
            ->has('quizzes.data.0', fn (Assert $item): Assert => $item
                ->hasAll(['uuid', 'title', 'items_count', 'created_at'])
                ->missing('raw_response')
                ->missing('source_image_hash')
                ->missing('source_image_path')
                ->missing('model')
                ->missing('input_tokens')
                ->missing('output_tokens')));
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
