<?php

declare(strict_types=1);

namespace App\MessageHandler\Subscription;

use App\Client\StripeApiClient;
use App\Exception\StripeException;
use App\Message\Subscription\SubscriptionPausedMessage;
use Stripe\Subscription;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SubscriptionPausedMessageHandler
{
    public function __construct(
        private StripeApiClient $stripeProvider,
    ) {
    }

    public function __invoke(SubscriptionPausedMessage $message): void
    {
        try {
            $stripeApiSubscription = $this->stripeProvider->getSubscriptionById($message->getSubscriptionId(), ['latest_invoice']);
        } catch (StripeException $e) {
            if ($e->retryable) {
                throw $e;
            }
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('Subscription retrieval from Stripe API failed', 0, $e);
        }

        if (Subscription::STATUS_PAUSED !== $stripeApiSubscription->status) {
            return;
        }

        // TODO: Handle customer.subscription.paused event
    }
}
