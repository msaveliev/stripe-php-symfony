<?php

declare(strict_types=1);

namespace App\Message\Subscription;

use App\Message\StripeEventAsyncMessageInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;

readonly class SubscriptionCreatedMessage implements MessageGroupAwareInterface, StripeEventAsyncMessageInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private string $eventId,
        private string $subscriptionId,
        private string $customerId,
        private string $userReference,
        private string $status,
        private array $metadata = [],
    ) {
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getUserReference(): string
    {
        return $this->userReference;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMessageGroupId(): ?string
    {
        return 'subscription'.$this->getSubscriptionId();
    }
}
