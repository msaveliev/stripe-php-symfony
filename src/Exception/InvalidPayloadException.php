<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidPayloadException extends PaymentException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message = 'Payload is invalid or malformed.',
        public readonly array $errors = [],
        public readonly ?string $payload = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 400, $previous);
    }
}
