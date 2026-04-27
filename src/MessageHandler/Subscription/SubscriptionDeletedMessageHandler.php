<?php

declare(strict_types=1);

namespace App\MessageHandler\Subscription;

use App\Client\StripeApiClient;
use App\Enum\SubscriptionStatus;
use App\Exception\StripeException;
use App\Message\Subscription\SubscriptionDeletedMessage;
use App\Repository\SubscriptionRepository;
use App\StateMachine\Context\SubscriptionTransitionContext;
use App\StateMachine\Exception\InvalidTransitionException;
use App\StateMachine\Exception\TransitionGuardFailedException;
use App\StateMachine\SubscriptionStateMachine;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SubscriptionDeletedMessageHandler
{
    public function __construct(
        private SubscriptionStateMachine $stateMachine,
        private SubscriptionRepository $subscriptionRepository,
        private StripeApiClient $stripeProvider,
    ) {
    }

    public function __invoke(SubscriptionDeletedMessage $message): void
    {
        $internalSubscription = $this->subscriptionRepository->findOneBy([
            'subscriptionId' => $message->getSubscriptionId(),
        ]);

        if (null === $internalSubscription) {
            throw new UnrecoverableMessageHandlingException('Subscription not found: '.$message->getSubscriptionId());
        }

        $user = $internalSubscription->getUser();

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

        try {
            $previousStatus = SubscriptionStatus::from($internalSubscription->getStatus());
            $newStatus = SubscriptionStatus::from($stripeApiSubscription->status);

            // Handle status change transitions using the state machine
            if ($previousStatus !== $newStatus) {
                $event = $this->stateMachine->determineEvent($previousStatus, $newStatus);

                if (null !== $event) {
                    $context = new SubscriptionTransitionContext(
                        user: $user,
                        subscription: $internalSubscription,
                        message: $message,
                        previousState: $previousStatus,
                        stripeData: $stripeApiSubscription
                    );

                    $resultingStatus = $this->stateMachine->apply($previousStatus, $event, $context);

                    $internalSubscription->setStatus($resultingStatus);
                } else {
                    throw new UnrecoverableMessageHandlingException('No state machine event for transition');
                }
            }
        } catch (InvalidTransitionException|TransitionGuardFailedException $e) {
            throw new UnrecoverableMessageHandlingException('State machine transition failed', 0, $e);
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('Failed to process subscription immediate cancellation', 0, $e);
        }
    }
}
