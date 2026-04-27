<?php

declare(strict_types=1);

namespace App\Event;

use Stripe\StripeObject;
use Symfony\Contracts\EventDispatcher\Event;

abstract class WebhookEventAbstract extends Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $eventId,
        private readonly string $type,
        private readonly array $data,
    ) {
    }

    abstract public function getObject(): StripeObject;

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return ((array) ($this->data['object'] ?? []))[$key] ?? $default;
    }

    abstract public function isProcessable(): bool;
}
