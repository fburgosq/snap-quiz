<?php

declare(strict_types=1);

namespace App\Services\Claude;

use App\Services\Claude\Exceptions\ClaudeException;

enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';

    public static function fromConfig(?string $provider = null): self
    {
        $raw = strtolower(trim($provider ?? (string) config('services.ai.provider', 'anthropic')));

        return match ($raw) {
            '', 'anthropic', 'claude' => self::Anthropic,
            'openai', 'chatgpt' => self::OpenAi,
            default => throw new ClaudeException(
                "Unsupported AI provider [{$raw}]. Supported providers: anthropic, openai."
            ),
        };
    }

    public function configKey(): string
    {
        return $this->value;
    }

    public function defaultBaseUrl(): string
    {
        return match ($this) {
            self::Anthropic => 'https://api.anthropic.com',
            self::OpenAi => 'https://api.openai.com',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::Anthropic => 'claude-haiku-4-5',
            self::OpenAi => 'gpt-4o-mini',
        };
    }

    public function endpoint(): string
    {
        return match ($this) {
            self::Anthropic => '/v1/messages',
            self::OpenAi => '/v1/chat/completions',
        };
    }
}
