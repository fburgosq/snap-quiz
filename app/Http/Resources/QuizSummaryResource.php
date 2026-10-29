<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight quiz resource for the Exercises listing page.
 *
 * Intentionally exposes only public summary attributes (`has_image` and `image_url`)
 * and never includes `source_image_path`, `source_image_hash`, `raw_response`, `model`,
 * disk names, or token counts.
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
     *     created_at: string|null,
     *     has_image: bool,
     *     image_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $hasImage = is_string($this->source_image_path) && $this->source_image_path !== '';

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'items_count' => (int) ($this->getAttribute('items_count') ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'has_image' => $hasImage,
            'image_url' => $hasImage ? route('exercises.image', $this->uuid) : null,
        ];
    }
}
