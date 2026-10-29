<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuizItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_id
 * @property int $position
 * @property string $sentence
 * @property int $blank_count
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quiz $quiz
 * @property-read Collection<int, QuizBlank> $blanks
 */
#[Fillable([
    'quiz_id',
    'position',
    'sentence',
    'blank_count',
    'reason',
])]
class QuizItem extends Model
{
    /** @use HasFactory<QuizItemFactory> */
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
            'blank_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return HasMany<QuizBlank, $this>
     */
    public function blanks(): HasMany
    {
        return $this->hasMany(QuizBlank::class)->orderBy('position');
    }
}
