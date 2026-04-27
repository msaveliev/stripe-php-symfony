<?php

declare(strict_types=1);

namespace App\Exception;

class PaymentAuthenticationException extends StripeException
{
    public function __construct(
        string $message = 'Payment provider authentication failed',
        ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            requestId: $requestId,
            retryable: false,
            httpStatus: 401,
            previous: $previous
        );
    }
}
