<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuizBlank;
use App\Models\QuizItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizBlank>
 */
class QuizBlankFactory extends Factory
{
    protected $model = QuizBlank::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_item_id' => QuizItem::factory(),
            'position' => 1,
        ];
    }
}
