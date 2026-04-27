<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Invoice\InvoicePaidEvent;
use App\Event\Invoice\InvoicePaymentFailedEvent;
use App\Messenger\Message\Invoice\InvoicePaidMessage;
use App\Messenger\Message\Invoice\InvoicePaymentFailedMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class InvoiceEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvoicePaidEvent::class => 'onInvoicePaid',
            InvoicePaymentFailedEvent::class => 'onInvoicePaymentFailed',
        ];
    }

    public function onInvoicePaid(InvoicePaidEvent $event): void
    {
        $invoice = $event->getObject();

        $parent = $invoice->parent;
        if (null === $parent) {
            throw new \RuntimeException('Invoice has no parent.');
        }
        $subscriptionDetails = $parent->subscription_details;
        if (null === $subscriptionDetails) {
            throw new \RuntimeException('Invoice parent has no subscription_details.');
        }
        $subscriptionId = $subscriptionDetails->subscription;
        if (!\is_string($subscriptionId)) {
            throw new \RuntimeException('Invoice subscription_details->subscription is not a string ID.');
        }

        $customerId = $invoice->customer;
        if (!\is_string($customerId)) {
            throw new \RuntimeException('Invoice customer is not a string ID.');
        }

        $this->messageBus->dispatch(new InvoicePaidMessage(
            eventId: $event->getEventId(),
            invoiceId: $invoice->id,
            customerId: $customerId,
            subscriptionId: $subscriptionId,
            amountPaid: $invoice->amount_paid,
            currency: $invoice->currency
        ));
    }

    public function onInvoicePaymentFailed(InvoicePaymentFailedEvent $event): void
    {
        $invoice = $event->getObject();

        $parent = $invoice->parent;
        if (null === $parent) {
            throw new \RuntimeException('Invoice has no parent.');
        }
        $subscriptionDetails = $parent->subscription_details;
        if (null === $subscriptionDetails) {
            throw new \RuntimeException('Invoice parent has no subscription_details.');
        }
        $subscriptionId = $subscriptionDetails->subscription;
        if (!\is_string($subscriptionId)) {
            throw new \RuntimeException('Invoice subscription_details->subscription is not a string ID.');
        }

        $customerId = $invoice->customer;
        if (!\is_string($customerId)) {
            throw new \RuntimeException('Invoice customer is not a string ID.');
        }

        $this->messageBus->dispatch(new InvoicePaymentFailedMessage(
            eventId: $event->getEventId(),
            invoiceId: $invoice->id,
            customerId: $customerId,
            subscriptionId: $subscriptionId,
            attemptCount: $invoice->attempt_count,
        ));
    }
}
