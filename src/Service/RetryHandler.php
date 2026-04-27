<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\RateLimitException;
use App\Exception\StripeException;
use Psr\Log\LoggerInterface;

final readonly class RetryHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private int $maxRetries = 2,
        private int $initialDelayMs = 500,
        private float $backoffMultiplier = 2.0,
        private int $maxDelayMs = 5000,
    ) {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        $delayMs = $this->initialDelayMs;

        for ($attempt = 0; $attempt <= $this->maxRetries; ++$attempt) {
            try {
                return $operation();
            } catch (StripeException $e) {
                if (!$e->retryable || $attempt === $this->maxRetries) {
                    throw $e;
                }

                $sleepMs = $e instanceof RateLimitException
                    ? $e->retryAfter * 1000
                    : (int) ($delayMs * ($this->backoffMultiplier ** $attempt));

                $sleepMs = min($sleepMs, $this->maxDelayMs);

                $this->logger->warning('Retrying Stripe API call after transient error', [
                    'attempt' => $attempt + 1,
                    'max_retries' => $this->maxRetries,
                    'delay_ms' => $sleepMs,
                    'error_class' => $e::class,
                    'error_message' => $e->getMessage(),
                    'request_id' => $e->requestId,
                ]);

                usleep($sleepMs * 1000);
            }
        }

        throw new \LogicException('RetryHandler loop exited unexpectedly');
    }
}
