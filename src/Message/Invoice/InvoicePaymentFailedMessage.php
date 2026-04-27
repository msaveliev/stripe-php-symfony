<?php

declare(strict_types=1);

namespace App\Message\Invoice;

use App\Message\StripeEventAsyncMessageInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;

readonly class InvoicePaymentFailedMessage implements MessageGroupAwareInterface, StripeEventAsyncMessageInterface
{
    public function __construct(
        private string $eventId,
        private string $invoiceId,
        private string $customerId,
        private string $subscriptionId,
        private int $attemptCount,
    ) {
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getMessageGroupId(): ?string
    {
        return 'subscription'.$this->getSubscriptionId();
    }
}
