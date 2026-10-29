<?php

declare(strict_types=1);

namespace App\Services\Claude\Exceptions;

use Throwable;

class ClaudeApiException extends ClaudeException
{
    public readonly int $status;

    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorType = null,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->status = $statusCode;
    }

    /**
     * Get the exception's context information for logging.
     *
     * @return array{status_code: int, error_type: ?string, request_id: ?string}
     */
    public function context(): array
    {
        return [
            'status_code' => $this->statusCode,
            'error_type' => $this->errorType,
            'request_id' => $this->requestId,
        ];
    }
}
