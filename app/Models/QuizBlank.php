<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuizBlankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_item_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QuizItem $item
 * @property-read Collection<int, QuizOption> $options
 * @property-read Collection<int, QuizOption> $correctOptions
 * @property-read Collection<int, QuizOption> $incorrectOptions
 */
#[Fillable([
    'quiz_item_id',
    'position',
])]
class QuizBlank extends Model
{
    /** @use HasFactory<QuizBlankFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<QuizItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(QuizItem::class, 'quiz_item_id');
    }

    /**
     * @return HasMany<QuizOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class)->orderBy('id');
    }

    /**
     * @return HasMany<QuizOption, $this>
     */
    public function correctOptions(): HasMany
    {
        return $this->hasMany(QuizOption::class)
            ->where('is_correct', true)
            ->orderBy('id');
    }

    /**
     * @return HasMany<QuizOption, $this>
     */
    public function incorrectOptions(): HasMany
    {
        return $this->hasMany(QuizOption::class)
            ->where('is_correct', false)
            ->orderBy('id');
    }
}
