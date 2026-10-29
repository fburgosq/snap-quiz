<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuizBlank;
use App\Models\QuizOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizOption>
 */
class QuizOptionFactory extends Factory
{
    protected $model = QuizOption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_blank_id' => QuizBlank::factory(),
            'text' => fake()->word(),
            'is_correct' => false,
        ];
    }

    /**
     * Indicate that the option is a correct answer.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_correct' => true,
        ]);
    }
}
