<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ConsumeOnlyValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Check if a message is being CONSUMED (has ReceivedStamp) vs. being DISPATCHED (no ReceivedStamp)
        $receivedStamp = $envelope->last(ReceivedStamp::class);
        if (!$receivedStamp instanceof ReceivedStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $message = $envelope->getMessage();
        $violations = $this->validator->validate($message);

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = \sprintf(
                    '%s: %s',
                    $violation->getPropertyPath(),
                    $violation->getMessage()
                );
            }

            $eventId = method_exists($message, 'getEventId')
                ? $message->getEventId()
                : 'unknown';

            $this->logger->error('Message validation failed during consumption', [
                'message_class' => $message::class,
                'event_id' => $eventId,
                'violations' => $errors,
                'transport' => $receivedStamp->getTransportName(),
            ]);

            throw new UnrecoverableMessageHandlingException(\sprintf('Message validation failed: %s', implode(', ', $errors)));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
