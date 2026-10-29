<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight quiz resource for the Exercises listing page.
 *
 * Intentionally exposes only public summary attributes and never includes
 * raw_response, source_image_hash, source_image_path, model, or token counts.
 *
 * @mixin Quiz
 */
class QuizSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     uuid: string,
     *     title: string,
     *     items_count: int,
     *     created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'items_count' => (int) ($this->getAttribute('items_count') ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
