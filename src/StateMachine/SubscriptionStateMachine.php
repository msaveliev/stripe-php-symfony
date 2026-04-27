<?php

declare(strict_types=1);

namespace App\StateMachine;

use App\Enum\SubscriptionEvent;
use App\Enum\SubscriptionStatus;
use App\StateMachine\Context\SubscriptionTransitionContext;
use App\StateMachine\Exception\InvalidTransitionException;
use App\StateMachine\Exception\TransitionGuardFailedException;
use Psr\Log\LoggerInterface;

final class SubscriptionStateMachine
{
    /** @var array<string, array<string, Transition>> */
    private array $transitionMap;

    public function __construct(
        private readonly Action\GrantSubscriptionAccess $grantAccess,
        private readonly Action\RevokeSubscriptionAccessImmediate $revokeAccessImmediate,
        private readonly Action\RevokeSubscriptionAccessByPeriodEnd $revokeAccessByPeriodEnd,
        private readonly Action\SetCancelAtFields $setCancelAtFields,
        private readonly Action\SetCancelAtPeriodEndFields $setCancelAtPeriodEndFields,
        private readonly Action\SetImmediateCancellationFields $setImmediateCancellationFields,
        private readonly LoggerInterface $logger,
    ) {
        $this->transitionMap = $this->buildTransitions();
    }

    /**
     * @return array<string, array<string, Transition>>
     */
    private function buildTransitions(): array
    {
        return [
            SubscriptionStatus::TRIALING->value => [
                SubscriptionEvent::TRIAL_ENDED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->grantAccess,
                    ],
                ),
                SubscriptionEvent::TRIAL_CANCELED->value => new Transition(
                    to: SubscriptionStatus::CANCELED,
                    actions: [
                        $this->setImmediateCancellationFields,
                        $this->revokeAccessImmediate,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_ENABLED->value => new Transition(
                    to: SubscriptionStatus::TRIALING,
                    actions: [
                        $this->setCancelAtFields,
                        $this->revokeAccessByPeriodEnd,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_DISABLED->value => new Transition(
                    to: SubscriptionStatus::TRIALING,
                    actions: [
                        $this->setCancelAtFields,
                        $this->grantAccess,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_PERIOD_END_ENABLED->value => new Transition(
                    to: SubscriptionStatus::TRIALING,
                    actions: [
                        $this->setCancelAtPeriodEndFields,
                        $this->revokeAccessByPeriodEnd,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_PERIOD_END_DISABLED->value => new Transition(
                    to: SubscriptionStatus::TRIALING,
                    actions: [
                        $this->setCancelAtPeriodEndFields,
                        $this->grantAccess,
                    ],
                ),

                SubscriptionEvent::PLAN_CHANGED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->grantAccess,
                    ],
                ),
            ],

            SubscriptionStatus::INCOMPLETE->value => [
                SubscriptionEvent::FIRST_PAYMENT_SUCCEEDED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->grantAccess,
                    ],
                ),
            ],

            SubscriptionStatus::ACTIVE->value => [
                SubscriptionEvent::RENEWAL_PAYMENT_FAILED->value => new Transition(
                    to: SubscriptionStatus::PAST_DUE,
                    actions: [
                        $this->grantAccess,
                    ],
                ),
                SubscriptionEvent::IMMEDIATE_CANCELLATION->value => new Transition(
                    to: SubscriptionStatus::CANCELED,
                    actions: [
                        $this->setImmediateCancellationFields,
                        $this->revokeAccessImmediate,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_ENABLED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->setCancelAtFields,
                        $this->revokeAccessByPeriodEnd,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_DISABLED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->setCancelAtFields,
                        $this->grantAccess,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_PERIOD_END_ENABLED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->setCancelAtPeriodEndFields,
                        $this->revokeAccessByPeriodEnd,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_PERIOD_END_DISABLED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->setCancelAtPeriodEndFields,
                        $this->grantAccess,
                    ],
                ),

                SubscriptionEvent::PLAN_CHANGED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->grantAccess,
                    ],
                ),
            ],

            SubscriptionStatus::PAST_DUE->value => [
                SubscriptionEvent::RENEWAL_PAYMENT_RECOVERED->value => new Transition(
                    to: SubscriptionStatus::ACTIVE,
                    actions: [
                        $this->grantAccess,
                    ],
                ),
                SubscriptionEvent::RENEWAL_PAYMENT_FAILED_PERMANENTLY->value => new Transition(
                    to: SubscriptionStatus::CANCELED,
                    actions: [
                        $this->setImmediateCancellationFields,
                        $this->revokeAccessImmediate,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_ENABLED->value => new Transition(
                    to: SubscriptionStatus::PAST_DUE,
                    actions: [
                        $this->setCancelAtFields,
                        $this->revokeAccessByPeriodEnd,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_CUSTOM_DATE_DISABLED->value => new Transition(
                    to: SubscriptionStatus::PAST_DUE,
                    actions: [
                        $this->setCancelAtFields,
                        $this->grantAccess,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_PERIOD_END_ENABLED->value => new Transition(
                    to: SubscriptionStatus::PAST_DUE,
                    actions: [
                        $this->setCancelAtPeriodEndFields,
                        $this->revokeAccessByPeriodEnd,
                    ],
                ),
                SubscriptionEvent::CANCEL_AT_PERIOD_END_DISABLED->value => new Transition(
                    to: SubscriptionStatus::PAST_DUE,
                    actions: [
                        $this->setCancelAtPeriodEndFields,
                        $this->grantAccess,
                    ],
                ),
            ],

            // Terminal states (no outbound transitions)
            SubscriptionStatus::CANCELED->value => [],
            SubscriptionStatus::UNPAID->value => [],
            SubscriptionStatus::INCOMPLETE_EXPIRED->value => [],
        ];
    }

    public function apply(
        SubscriptionStatus $currentState,
        SubscriptionEvent $event,
        SubscriptionTransitionContext $context,
    ): SubscriptionStatus {
        try {
            /** @var Transition|null $transition */
            $transition = $this->transitionMap[$currentState->value][$event->value] ?? null;

            if (null === $transition) {
                throw new InvalidTransitionException("No transition from $currentState->value on event $event->value");
            }

            if (!$transition->isAllowed($context)) {
                throw new TransitionGuardFailedException("Guard failed for transition from {$currentState->value} on event {$event->value}");
            }

            $transition->execute($context);

            $this->logger->info('Subscription state transition succeeded', [
                'subscription_id' => $context->subscription->getSubscriptionId(),
                'user_id' => $context->user->getId(),
                'from_state' => $currentState->value,
                'to_state' => $transition->to->value,
                'event' => $event->value,
            ]);

            return $transition->to;
        } catch (InvalidTransitionException|TransitionGuardFailedException $e) {
            $this->logger->warning('Subscription state transition failed', [
                'subscription_id' => $context->subscription->getSubscriptionId(),
                'user_id' => $context->user->getId(),
                'from_state' => $currentState->value,
                'event' => $event->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Determines the appropriate SubscriptionEvent based on state transition.
     *
     * Maps Stripe status changes to domain events that trigger business logic.
     *
     * @param SubscriptionStatus $from Previous subscription status
     * @param SubscriptionStatus $to   New subscription status
     *
     * @return SubscriptionEvent|null Event to apply, or null if no event needed
     */
    public function determineEvent(
        SubscriptionStatus $from,
        SubscriptionStatus $to,
    ): ?SubscriptionEvent {
        return match ([$from, $to]) {
            [SubscriptionStatus::TRIALING, SubscriptionStatus::ACTIVE] => SubscriptionEvent::TRIAL_ENDED,
            [SubscriptionStatus::TRIALING, SubscriptionStatus::CANCELED] => SubscriptionEvent::TRIAL_CANCELED,

            [SubscriptionStatus::TRIALING, SubscriptionStatus::PAST_DUE] => SubscriptionEvent::RENEWAL_PAYMENT_FAILED,
            [SubscriptionStatus::PAST_DUE, SubscriptionStatus::ACTIVE] => SubscriptionEvent::RENEWAL_PAYMENT_RECOVERED,

            [SubscriptionStatus::ACTIVE, SubscriptionStatus::CANCELED] => SubscriptionEvent::IMMEDIATE_CANCELLATION,
            [SubscriptionStatus::ACTIVE, SubscriptionStatus::PAST_DUE] => SubscriptionEvent::RENEWAL_PAYMENT_FAILED,
            [SubscriptionStatus::PAST_DUE, SubscriptionStatus::CANCELED] => SubscriptionEvent::RENEWAL_PAYMENT_FAILED_PERMANENTLY,

            [SubscriptionStatus::INCOMPLETE, SubscriptionStatus::ACTIVE] => SubscriptionEvent::FIRST_PAYMENT_SUCCEEDED,
            [SubscriptionStatus::INCOMPLETE, SubscriptionStatus::TRIALING] => SubscriptionEvent::FIRST_PAYMENT_SUCCEEDED,

            default => null,
        };
    }
}
