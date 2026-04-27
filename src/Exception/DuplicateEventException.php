<?php

declare(strict_types=1);

namespace App\Exception;

class DuplicateEventException extends PaymentException
{
    public function __construct(
        string $message = 'Event has already been processed.',
        public readonly ?string $eventId = null,
        ?\Throwable $previous = null,
    ) {
        $fullMessage = $message;
        if (null !== $eventId) {
            $fullMessage .= " Event ID: $eventId";
        }

        parent::__construct($fullMessage, 409, $previous);
    }
}
