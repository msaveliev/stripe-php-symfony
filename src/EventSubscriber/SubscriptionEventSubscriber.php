<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Client\StripeApiClient;
use App\Event\Subscription\SubscriptionCreatedEvent;
use App\Event\Subscription\SubscriptionDeletedEvent;
use App\Event\Subscription\SubscriptionPausedEvent;
use App\Event\Subscription\SubscriptionTrialEndingEvent;
use App\Event\Subscription\SubscriptionUpdatedEvent;
use App\Message\Subscription\SubscriptionCreatedMessage;
use App\Message\Subscription\SubscriptionDeletedMessage;
use App\Message\Subscription\SubscriptionPausedMessage;
use App\Message\Subscription\SubscriptionTrialEndingMessage;
use App\Message\Subscription\SubscriptionUpdatedMessage;
use Stripe\Subscription;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class SubscriptionEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private StripeApiClient $stripeProvider,
        private MessageBusInterface $messageBus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SubscriptionCreatedEvent::class => 'onSubscriptionCreated',
            SubscriptionUpdatedEvent::class => 'onSubscriptionUpdated',
            SubscriptionPausedEvent::class => 'onSubscriptionPaused',
            SubscriptionDeletedEvent::class => 'onSubscriptionDeleted',
            SubscriptionTrialEndingEvent::class => 'onSubscriptionTrialEnding',
        ];
    }

    public function onSubscriptionCreated(SubscriptionCreatedEvent $event): void
    {
        $subscription = $event->getObject();

        $userReference = $this->resolveUserReference($subscription);
        $customerId = $this->resolveCustomerId($subscription);

        $this->messageBus->dispatch(new SubscriptionCreatedMessage(
            eventId: $event->getEventId(),
            subscriptionId: $subscription->id,
            customerId: $customerId,
            userReference: $userReference,
            status: $subscription->status,
            metadata: $subscription->metadata->toArray(),
        ));
    }

    public function onSubscriptionUpdated(SubscriptionUpdatedEvent $event): void
    {
        $subscription = $event->getObject();

        $this->messageBus->dispatch(new SubscriptionUpdatedMessage(
            eventId: $event->getEventId(),
            subscriptionId: $subscription->id,
            customerId: $this->resolveCustomerId($subscription)
        ));
    }

    public function onSubscriptionDeleted(SubscriptionDeletedEvent $event): void
    {
        $subscription = $event->getObject();

        $this->messageBus->dispatch(new SubscriptionDeletedMessage(
            eventId: $event->getEventId(),
            subscriptionId: $subscription->id,
            customerId: $this->resolveCustomerId($subscription)
        ));
    }

    public function onSubscriptionPaused(SubscriptionPausedEvent $event): void
    {
        $subscription = $event->getObject();

        $this->messageBus->dispatch(new SubscriptionPausedMessage(
            eventId: $event->getEventId(),
            subscriptionId: $subscription->id,
            customerId: $this->resolveCustomerId($subscription)
        ));
    }

    public function onSubscriptionTrialEnding(SubscriptionTrialEndingEvent $event): void
    {
        $subscription = $event->getObject();

        $this->messageBus->dispatch(new SubscriptionTrialEndingMessage(
            eventId: $event->getEventId(),
            subscriptionId: $subscription->id,
            customerId: $this->resolveCustomerId($subscription)
        ));
    }

    private function resolveCustomerId(Subscription $subscription): string
    {
        $customer = $subscription->customer;
        if (!\is_string($customer)) {
            throw new \RuntimeException('Subscription customer is not a string ID.');
        }

        return $customer;
    }

    private function resolveUserReference(Subscription $subscription): string
    {
        try {
            $customer = $this->stripeProvider->getCustomer($this->resolveCustomerId($subscription));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to fetch customer from Stripe API', 0, $e);
        }

        $email = $customer->email;
        if (null === $email) {
            throw new \RuntimeException('Customer has no email address.');
        }

        return $email;
    }
}
