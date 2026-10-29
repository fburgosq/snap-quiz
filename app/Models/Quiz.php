<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $title
 * @property string|null $source_image_path
 * @property string|null $source_image_hash
 * @property string|null $model
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property array<string, mixed>|null $raw_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 * @property-read Collection<int, QuizItem> $items
 */
#[Fillable([
    'uuid',
    'user_id',
    'title',
    'source_image_path',
    'source_image_hash',
    'model',
    'input_tokens',
    'output_tokens',
    'raw_response',
])]
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Quiz $quiz): void {
            if (empty($quiz->uuid)) {
                $quiz->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'raw_response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QuizItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuizItem::class)->orderBy('position');
    }

    /**
     * Scope a query to quizzes owned by the given user (or guest when null).
     *
     * @param  Builder<Quiz>  $query
     * @return Builder<Quiz>
     */
    public function scopeForUser(Builder $query, User|int|null $user): Builder
    {
        if ($user instanceof User) {
            return $query->where('user_id', $user->id);
        }

        if (is_int($user)) {
            return $query->where('user_id', $user);
        }

        return $query->whereNull('user_id');
    }

    /**
     * Rebuild the original Claude JSON shape ({title, exercise[]}) from the database.
     *
     * @return array{
     *     title: string,
     *     exercise: list<array{
     *         sentence: string,
     *         correct: string|list<string>,
     *         incorrect: list<string>,
     *         reason: string
     *     }>
     * }
     */
    public function toExerciseArray(): array
    {
        $items = $this->items()
            ->with(['blanks.options'])
            ->get();

        $exercises = [];

        foreach ($items as $item) {
            $correctAnswers = [];
            $incorrectAnswers = [];

            foreach ($item->blanks as $blank) {
                foreach ($blank->options as $option) {
                    if ($option->is_correct) {
                        $correctAnswers[] = $option->text;
                    } else {
                        $incorrectAnswers[] = $option->text;
                    }
                }
            }

            $exercises[] = [
                'sentence' => $item->sentence,
                'correct' => count($correctAnswers) === 1
                    ? $correctAnswers[0]
                    : $correctAnswers,
                'incorrect' => $incorrectAnswers,
                'reason' => $item->reason,
            ];
        }

        return [
            'title' => $this->title,
            'exercise' => $exercises,
        ];
    }
}
