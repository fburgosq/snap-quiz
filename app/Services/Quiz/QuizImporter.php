<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\Quiz;
use App\Models\QuizBlank;
use App\Models\QuizItem;
use App\Models\QuizOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class QuizImporter
{
    /**
     * Find an existing quiz matching the given image hash and user.
     */
    public static function findDuplicate(string $imageHash, ?int $userId = null): ?Quiz
    {
        if (trim($imageHash) === '') {
            return null;
        }

        return Quiz::query()
            ->where('source_image_hash', $imageHash)
            ->forUser($userId)
            ->latest('id')
            ->first();
    }

    /**
     * Import a decoded Claude exercise payload into the database within a transaction.
     *
     * @param  array<string, mixed>  $claudeJson
     * @param  array{
     *     source_image_path?: ?string,
     *     source_image_hash?: ?string,
     *     model?: ?string,
     *     input_tokens?: ?int,
     *     output_tokens?: ?int
     * }  $meta
     *
     * @throws InvalidArgumentException
     */
    public function import(array $claudeJson, ?int $userId = null, array $meta = []): Quiz
    {
        if (! isset($claudeJson['title']) || ! is_string($claudeJson['title']) || trim($claudeJson['title']) === '') {
            throw new InvalidArgumentException('Quiz payload must contain a non-empty string "title".');
        }

        if (! isset($claudeJson['exercise']) || ! is_array($claudeJson['exercise'])) {
            throw new InvalidArgumentException('Quiz payload must contain an "exercise" array.');
        }

        $title = $claudeJson['title'];
        /** @var array<int, mixed> $exercises */
        $exercises = array_values($claudeJson['exercise']);

        return DB::transaction(function () use ($claudeJson, $title, $exercises, $userId, $meta): Quiz {
            $quiz = Quiz::query()->create([
                'user_id' => $userId,
                'title' => $title,
                'source_image_path' => isset($meta['source_image_path']) ? (string) $meta['source_image_path'] : null,
                'source_image_hash' => isset($meta['source_image_hash']) ? (string) $meta['source_image_hash'] : null,
                'model' => isset($meta['model']) ? (string) $meta['model'] : null,
                'input_tokens' => isset($meta['input_tokens']) ? (int) $meta['input_tokens'] : null,
                'output_tokens' => isset($meta['output_tokens']) ? (int) $meta['output_tokens'] : null,
                'raw_response' => $claudeJson,
            ]);

            $now = now();

            foreach ($exercises as $zeroIndex => $rawItem) {
                $itemPosition = $zeroIndex + 1;
                $validatedItem = $this->validateExerciseItem($rawItem, $zeroIndex);
                $blankCount = $this->countBlanks($validatedItem['sentence']);

                $quizItem = QuizItem::query()->create([
                    'quiz_id' => $quiz->id,
                    'position' => $itemPosition,
                    'sentence' => $validatedItem['sentence'],
                    'blank_count' => $blankCount,
                    'reason' => $validatedItem['reason'],
                ]);

                $correctByBlank = $this->distributeCorrect($validatedItem['correct'], $blankCount, $itemPosition);
                $incorrectByBlank = $this->distributeIncorrect($validatedItem['incorrect'], $blankCount, $itemPosition);

                $optionRows = [];

                for ($blankPosition = 1; $blankPosition <= $blankCount; $blankPosition++) {
                    $blank = QuizBlank::query()->create([
                        'quiz_item_id' => $quizItem->id,
                        'position' => $blankPosition,
                    ]);

                    $seen = [];

                    foreach ($correctByBlank[$blankPosition] ?? [] as $correctText) {
                        $normalizedKey = $this->normalizeOptionKey($correctText);

                        if ($normalizedKey === '' || isset($seen[$normalizedKey])) {
                            continue;
                        }

                        $seen[$normalizedKey] = true;
                        $optionRows[] = [
                            'quiz_blank_id' => $blank->id,
                            'text' => $correctText,
                            'is_correct' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    foreach ($incorrectByBlank[$blankPosition] ?? [] as $incorrectText) {
                        $normalizedKey = $this->normalizeOptionKey($incorrectText);

                        if ($normalizedKey === '' || isset($seen[$normalizedKey])) {
                            continue;
                        }

                        $seen[$normalizedKey] = true;
                        $optionRows[] = [
                            'quiz_blank_id' => $blank->id,
                            'text' => $incorrectText,
                            'is_correct' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($optionRows !== []) {
                    QuizOption::query()->insert($optionRows);
                }
            }

            return $quiz;
        });
    }

    /**
     * Validate a single exercise item and normalize its types.
     *
     * @return array{
     *     sentence: string,
     *     correct: string|list<string>,
     *     incorrect: list<string>,
     *     reason: string
     * }
     */
    private function validateExerciseItem(mixed $rawItem, int $index): array
    {
        if (! is_array($rawItem)) {
            throw new InvalidArgumentException("Exercise item at index {$index} must be an array.");
        }

        if (! isset($rawItem['sentence']) || ! is_string($rawItem['sentence']) || trim($rawItem['sentence']) === '') {
            throw new InvalidArgumentException("Exercise item at index {$index} must have a non-empty string \"sentence\".");
        }

        if (! isset($rawItem['reason']) || ! is_string($rawItem['reason']) || trim($rawItem['reason']) === '') {
            throw new InvalidArgumentException("Exercise item at index {$index} must have a non-empty string \"reason\".");
        }

        $rawCorrect = $rawItem['correct'] ?? null;

        if (is_string($rawCorrect) && trim($rawCorrect) !== '') {
            $correct = $rawCorrect;
        } elseif (is_array($rawCorrect) && $rawCorrect !== []) {
            $correct = [];

            foreach ($rawCorrect as $value) {
                if (! is_string($value) || trim($value) === '') {
                    throw new InvalidArgumentException("Exercise item at index {$index} has an invalid \"correct\" entry.");
                }

                $correct[] = $value;
            }
        } else {
            throw new InvalidArgumentException("Exercise item at index {$index} must have a valid \"correct\" answer.");
        }

        if (! isset($rawItem['incorrect']) || ! is_array($rawItem['incorrect'])) {
            throw new InvalidArgumentException("Exercise item at index {$index} must have an \"incorrect\" array.");
        }

        $incorrect = [];

        foreach ($rawItem['incorrect'] as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("Exercise item at index {$index} has a non-string \"incorrect\" option.");
            }

            $incorrect[] = $value;
        }

        return [
            'sentence' => $rawItem['sentence'],
            'correct' => $correct,
            'incorrect' => $incorrect,
            'reason' => $rawItem['reason'],
        ];
    }

    /**
     * Count occurrences of "____" in a sentence, returning at least 1.
     */
    private function countBlanks(string $sentence): int
    {
        return max(1, substr_count($sentence, '____'));
    }

    /**
     * Distribute correct answer(s) across blanks 1..blank_count.
     *
     * @param  string|list<string>  $correct
     * @return array<int, list<string>>
     */
    private function distributeCorrect(string|array $correct, int $blankCount, int $itemPosition): array
    {
        /** @var array<int, list<string>> $distributed */
        $distributed = array_fill(1, $blankCount, []);

        if (is_string($correct)) {
            $distributed[1] = [$correct];

            return $distributed;
        }

        if ($blankCount > 1 && count($correct) === $blankCount) {
            foreach ($correct as $zeroIndex => $answer) {
                $distributed[$zeroIndex + 1] = [$answer];
            }

            return $distributed;
        }

        if ($blankCount === 1) {
            $distributed[1] = $correct;

            return $distributed;
        }

        Log::warning('QuizImporter correct answer count does not match blank count; assigning all to blank 1.', [
            'item_position' => $itemPosition,
            'blank_count' => $blankCount,
            'correct_count' => count($correct),
        ]);

        $distributed[1] = $correct;

        return $distributed;
    }

    /**
     * Distribute incorrect options in chunks of 4 across blanks 1..blank_count.
     *
     * @param  list<string>  $incorrect
     * @return array<int, list<string>>
     */
    private function distributeIncorrect(array $incorrect, int $blankCount, int $itemPosition): array
    {
        /** @var array<int, list<string>> $distributed */
        $distributed = array_fill(1, $blankCount, []);
        $chunks = array_chunk($incorrect, 4);

        if (count($incorrect) % 4 !== 0 || count($chunks) !== $blankCount) {
            Log::warning('QuizImporter incorrect option count does not match 4x blank count.', [
                'item_position' => $itemPosition,
                'blank_count' => $blankCount,
                'incorrect_count' => count($incorrect),
            ]);
        }

        for ($blankPosition = 1; $blankPosition <= $blankCount; $blankPosition++) {
            $distributed[$blankPosition] = $chunks[$blankPosition - 1] ?? [];
        }

        return $distributed;
    }

    private function normalizeOptionKey(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}
