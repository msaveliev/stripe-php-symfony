<?php

declare(strict_types=1);

namespace App\Webhook;

use App\Event\WebhookEventAbstract;
use App\Exception\PaymentException;

final readonly class WebhookEventFactory
{
    /**
     * @param array<string, class-string<WebhookEventAbstract>> $eventMap
     */
    public function __construct(
        private array $eventMap,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws PaymentException
     */
    public function create(string $id, string $type, array $data): WebhookEventAbstract
    {
        if (!isset($this->eventMap[$type])) {
            throw new PaymentException("Unknown webhook event type: $type");
        }

        $class = $this->eventMap[$type];

        return new $class($id, $type, $data);
    }

    public function supports(string $type): bool
    {
        return isset($this->eventMap[$type]);
    }
}
