<?php

declare(strict_types=1);

namespace App\Exception;

class CardException extends StripeException
{
    public function __construct(
        string $message = 'Payment card declined',
        ?string $requestId = null,
        public readonly ?string $declineCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            requestId: $requestId,
            retryable: false,
            httpStatus: 402,
            previous: $previous
        );
    }
}
