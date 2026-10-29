<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quiz;
use App\Models\QuizBlank;
use App\Models\QuizItem;
use App\Models\QuizOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quiz
 */
class QuizResource extends JsonResource
{
    private bool $isDuplicate = false;

    public function markDuplicate(bool $duplicate = true): self
    {
        $this->isDuplicate = $duplicate;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'created_at' => $this->created_at?->toISOString(),
            'duplicate' => $this->isDuplicate,
            'items' => $this->whenLoaded('items', fn (): array => $this->formatItems()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatItems(): array
    {
        $formattedItems = [];

        /** @var QuizItem $item */
        foreach ($this->items as $item) {
            $formattedBlanks = [];

            if ($item->relationLoaded('blanks')) {
                /** @var QuizBlank $blank */
                foreach ($item->blanks as $blank) {
                    $formattedOptions = [];

                    if ($blank->relationLoaded('options')) {
                        /** @var QuizOption $option */
                        foreach ($blank->options as $option) {
                            $formattedOptions[] = [
                                'text' => $option->text,
                                'is_correct' => $option->is_correct,
                            ];
                        }
                    }

                    $formattedBlanks[] = [
                        'position' => $blank->position,
                        'options' => $formattedOptions,
                    ];
                }
            }

            $formattedItems[] = [
                'position' => $item->position,
                'sentence' => $item->sentence,
                'blank_count' => $item->blank_count,
                'reason' => $item->reason,
                'blanks' => $formattedBlanks,
            ];
        }

        return $formattedItems;
    }
}
