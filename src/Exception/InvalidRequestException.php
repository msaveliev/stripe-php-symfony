<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidRequestException extends StripeException
{
    public function __construct(
        string $message = 'Invalid request to payment provider',
        ?string $requestId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $param = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            requestId: $requestId,
            retryable: false,
            httpStatus: 400,
            previous: $previous
        );
    }
}
