<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidSignatureException extends PaymentException
{
    public function __construct(
        string $message = 'Signature verification failed.',
        public readonly ?string $signature = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $previous);
    }
}
