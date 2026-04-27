<?php

declare(strict_types=1);

namespace App\Message\Subscription;

use App\Message\StripeEventSyncMessageInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;

readonly class SubscriptionTrialEndingMessage implements MessageGroupAwareInterface, StripeEventSyncMessageInterface
{
    public function __construct(
        private string $eventId,
        private string $subscriptionId,
        private string $customerId,
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

    public function getMessageGroupId(): ?string
    {
        return 'subscription'.$this->getSubscriptionId();
    }
}
