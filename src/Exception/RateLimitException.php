<?php

declare(strict_types=1);

namespace App\Exception;

class RateLimitException extends StripeException
{
    public int $retryAfter {
        get {
            return $this->retryAfter;
        }
    }

    public function __construct(
        string $message = 'Payment provider rate limit exceeded',
        ?string $requestId = null,
        int $retryAfter = 60,
        ?\Throwable $previous = null,
    ) {
        $this->retryAfter = $retryAfter;
        parent::__construct(
            message: $message,
            requestId: $requestId,
            retryable: true,
            httpStatus: 429,
            previous: $previous
        );
    }
}
