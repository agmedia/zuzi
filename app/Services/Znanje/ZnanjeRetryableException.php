<?php

namespace App\Services\Znanje;

use RuntimeException;
use Throwable;

class ZnanjeRetryableException extends RuntimeException
{
    private int $responseStatus;

    private int $retryAfterSeconds;

    public function __construct(
        string $message,
        int $responseStatus = 503,
        int $retryAfterSeconds = 2,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->responseStatus = $responseStatus === 429 ? 429 : 503;
        $this->retryAfterSeconds = min(max($retryAfterSeconds, 1), 120);
    }

    public function responseStatus(): int
    {
        return $this->responseStatus;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
