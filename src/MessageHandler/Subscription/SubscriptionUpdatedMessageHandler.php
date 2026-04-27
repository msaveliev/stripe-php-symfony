<?php

declare(strict_types=1);

namespace App\MessageHandler\Subscription;

use App\Client\StripeApiClient;
use App\Enum\SubscriptionEvent;
use App\Enum\SubscriptionStatus;
use App\Exception\StripeException;
use App\Message\Subscription\SubscriptionUpdatedMessage;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\StateMachine\Context\SubscriptionTransitionContext;
use App\StateMachine\Exception\InvalidTransitionException;
use App\StateMachine\Exception\TransitionGuardFailedException;
use App\StateMachine\SubscriptionStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SubscriptionUpdatedMessageHandler
{
    public function __construct(
        private SubscriptionStateMachine $stateMachine,
        private UserRepository $userRepository,
        private SubscriptionRepository $subscriptionRepository,
        private StripeApiClient $stripeProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SubscriptionUpdatedMessage $message): void
    {
        $user = $this->userRepository->findOneBy([
            'stripeCustomerId' => $message->getCustomerId(),
        ]);
        $internalSubscription = $this->subscriptionRepository->findOneBy([
            'subscriptionId' => $message->getSubscriptionId(),
        ]);

        if (null === $user) {
            throw new UnrecoverableMessageHandlingException('User not found for customer: '.$message->getCustomerId());
        }

        if (null === $internalSubscription) {
            throw new UnrecoverableMessageHandlingException('Subscription not found: '.$message->getSubscriptionId());
        }

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

        // TODO: Sync Stripe subscription data to internal subscription entity before state machine transition

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

            // Handle the cancel_at_period_end toggle (field-based event). Stripe Dashboard
            $stripeCancelAtPeriodEnd = $stripeApiSubscription->cancel_at_period_end;
            $localCancelAtPeriodEnd = $internalSubscription->getCancelAtPeriodEnd();
            if ($stripeCancelAtPeriodEnd !== $localCancelAtPeriodEnd) {
                $event = $stripeCancelAtPeriodEnd
                    ? SubscriptionEvent::CANCEL_AT_PERIOD_END_ENABLED
                    : SubscriptionEvent::CANCEL_AT_PERIOD_END_DISABLED;

                $context = new SubscriptionTransitionContext(
                    user: $user,
                    subscription: $internalSubscription,
                    message: $message,
                    previousState: $newStatus,
                    stripeData: $stripeApiSubscription
                );

                try {
                    $this->stateMachine->apply(
                        currentState: $newStatus,
                        event: $event,
                        context: $context
                    );
                } catch (InvalidTransitionException $e) {
                    throw new UnrecoverableMessageHandlingException($event->name.' not applicable', 0, $e);
                }
            }

            // Handle the cancel_at_a_custom_date toggle (field-based event). Customer Portal | Stripe Dashboard
            $stripeCancelAt = $stripeApiSubscription->cancel_at;
            $localCancelAt = $internalSubscription->getCancelAt()?->getTimestamp();
            if (false === $stripeCancelAtPeriodEnd && ($stripeCancelAt !== $localCancelAt)) {
                $event = null !== $stripeCancelAt
                    ? SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_ENABLED
                    : SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_DISABLED;

                $context = new SubscriptionTransitionContext(
                    user: $user,
                    subscription: $internalSubscription,
                    message: $message,
                    previousState: $newStatus,
                    stripeData: $stripeApiSubscription
                );

                try {
                    $this->stateMachine->apply(
                        currentState: $newStatus,
                        event: $event,
                        context: $context
                    );
                } catch (InvalidTransitionException $e) {
                    throw new UnrecoverableMessageHandlingException($event->name.' not applicable', 0, $e);
                }
            }

            // Handle plan changes (upgrade/downgrade)
            $subscriptionItem = $stripeApiSubscription->items->first();
            if (null !== $subscriptionItem && $internalSubscription->getPriceId() !== $subscriptionItem->price->id) {
                $context = new SubscriptionTransitionContext(
                    user: $user,
                    subscription: $internalSubscription,
                    message: $message,
                    previousState: $newStatus,
                    stripeData: $stripeApiSubscription
                );

                try {
                    $this->stateMachine->apply(
                        $newStatus,
                        SubscriptionEvent::PLAN_CHANGED,
                        $context
                    );
                } catch (InvalidTransitionException $e) {
                }
            }

            $this->entityManager->persist($user);
            $this->entityManager->persist($internalSubscription);
        } catch (InvalidTransitionException|TransitionGuardFailedException $e) {
            throw new UnrecoverableMessageHandlingException('State machine transition failed', 0, $e);
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('Failed to process subscription update', 0, $e);
        }
    }
}
