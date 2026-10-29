<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizItem>
 */
class QuizItemFactory extends Factory
{
    protected $model = QuizItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'position' => 1,
            'sentence' => 'The capital of France is ____.',
            'blank_count' => 1,
            'reason' => 'Paris is the capital of France.',
        ];
    }
}
