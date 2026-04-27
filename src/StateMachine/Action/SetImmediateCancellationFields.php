<?php

declare(strict_types=1);

namespace App\StateMachine\Action;

use App\StateMachine\Context\SubscriptionTransitionContext;

final readonly class SetImmediateCancellationFields
{
    public function __invoke(SubscriptionTransitionContext $context): void
    {
        $subscription = $context->subscription;
        $stripeData = $context->stripeData;

        if (null === $stripeData) {
            return;
        }

        if (null !== $stripeData->canceled_at) {
            $canceledAt = \DateTimeImmutable::createFromFormat('U', (string) $stripeData->canceled_at);
            if ($canceledAt instanceof \DateTimeImmutable) {
                $subscription->setCanceledAt($canceledAt);
            }
        }

        if (null !== $stripeData->ended_at) {
            $endedAt = \DateTimeImmutable::createFromFormat('U', (string) $stripeData->ended_at);
            if ($endedAt instanceof \DateTimeImmutable) {
                $subscription->setEndedAt($endedAt);
            }
        }
    }
}
