<?php

declare(strict_types=1);

namespace App\Exception;

class NetworkException extends StripeException
{
    public function __construct(
        string $message = 'Failed to connect to payment provider API',
        ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            requestId: $requestId,
            retryable: true,
            httpStatus: 503,
            previous: $previous
        );
    }
}
