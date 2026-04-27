<?php

declare(strict_types=1);

namespace App\Exception;

class ApiException extends StripeException
{
    public function __construct(
        string $message = 'Payment provider API error',
        ?string $requestId = null,
        public readonly ?string $errorCode = null,
        public readonly ?int $httpStatus = null,
        bool $retryable = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            requestId: $requestId,
            retryable: $retryable,
            httpStatus: $httpStatus ?? 500,
            previous: $previous
        );
    }
}
