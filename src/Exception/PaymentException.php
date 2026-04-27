<?php

declare(strict_types=1);

namespace App\Exception;

class PaymentException extends \RuntimeException
{
    public function __construct(
        string $message = 'An unexpected payment error occurred.',
        int $code = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
