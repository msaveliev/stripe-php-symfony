<?php

declare(strict_types=1);

namespace App\Message\Invoice;

use App\Message\StripeEventAsyncMessageInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;

readonly class InvoicePaidMessage implements MessageGroupAwareInterface, StripeEventAsyncMessageInterface
{
    public function __construct(
        private string $eventId,
        private string $invoiceId,
        private string $customerId,
        private string $subscriptionId,
        private int $amountPaid,
        private string $currency,
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

    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function getAmountPaid(): int
    {
        return $this->amountPaid;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMessageGroupId(): ?string
    {
        return 'subscription'.$this->getSubscriptionId();
    }
}
