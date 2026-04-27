<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Subscription;

use App\Client\StripeApiClient;
use App\Exception\StripeException;
use App\Messenger\Message\Subscription\SubscriptionTrialEndingMessage;
use Stripe\Subscription;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SubscriptionTrialEndingMessageHandler
{
    public function __construct(
        private StripeApiClient $stripeProvider,
    ) {
    }

    public function __invoke(SubscriptionTrialEndingMessage $message): void
    {
        try {
            $stripeApiSubscription = $this->stripeProvider->getSubscriptionById($message->getSubscriptionId());
        } catch (StripeException $e) {
            if ($e->retryable) {
                throw $e;
            }
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('Subscription retrieval from Stripe API failed', 0, $e);
        }

        if (Subscription::STATUS_TRIALING !== $stripeApiSubscription->status) {
            return;
        }

        // TODO: Handle customer.subscription.trial_will_end event idempotently
    }
}
