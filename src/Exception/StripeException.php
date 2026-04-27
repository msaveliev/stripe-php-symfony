<?php

declare(strict_types=1);

namespace App\Exception;

abstract class StripeException extends PaymentException
{
    public function __construct(
        string $message,
        public readonly ?string $requestId = null,
        public readonly bool $retryable = false,
        int $httpStatus = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}
