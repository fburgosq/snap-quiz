<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizBlank;
use App\Models\QuizItem;
use App\Models\QuizOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'title' => fake()->sentence(3),
            'source_image_path' => null,
            'source_image_hash' => hash('sha256', fake()->unique()->uuid()),
            'model' => 'claude-haiku-4-5',
            'input_tokens' => 450,
            'output_tokens' => 120,
            'raw_response' => null,
        ];
    }

    /**
     * Configure the quiz to have N items, each with 1 blank, 1 correct option, and 4 incorrect options.
     */
    public function withItems(int $count = 3): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count): void {
            for ($position = 1; $position <= $count; $position++) {
                $item = QuizItem::factory()->create([
                    'quiz_id' => $quiz->id,
                    'position' => $position,
                    'sentence' => "Sample exercise {$position} with a ____ blank.",
                    'blank_count' => 1,
                    'reason' => "Explanation for exercise {$position}.",
                ]);

                $blank = QuizBlank::factory()->create([
                    'quiz_item_id' => $item->id,
                    'position' => 1,
                ]);

                QuizOption::factory()->correct()->create([
                    'quiz_blank_id' => $blank->id,
                    'text' => "correct-{$position}",
                ]);

                for ($optionIndex = 1; $optionIndex <= 4; $optionIndex++) {
                    QuizOption::factory()->create([
                        'quiz_blank_id' => $blank->id,
                        'text' => "distractor-{$position}-{$optionIndex}",
                        'is_correct' => false,
                    ]);
                }
            }
        });
    }
}
