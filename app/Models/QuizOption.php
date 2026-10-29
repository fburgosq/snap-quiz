<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuizOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_blank_id
 * @property string $text
 * @property bool $is_correct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QuizBlank $blank
 */
#[Fillable([
    'quiz_blank_id',
    'text',
    'is_correct',
])]
class QuizOption extends Model
{
    /** @use HasFactory<QuizOptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<QuizBlank, $this>
     */
    public function blank(): BelongsTo
    {
        return $this->belongsTo(QuizBlank::class, 'quiz_blank_id');
    }
}
