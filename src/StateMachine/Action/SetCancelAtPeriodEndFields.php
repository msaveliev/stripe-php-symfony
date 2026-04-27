<?php

declare(strict_types=1);

namespace App\StateMachine\Action;

use App\StateMachine\Context\SubscriptionTransitionContext;

final readonly class SetCancelAtPeriodEndFields
{
    public function __invoke(SubscriptionTransitionContext $context): void
    {
        $subscription = $context->subscription;
        $stripeData = $context->stripeData;

        if (null === $stripeData) {
            return;
        }

        $cancelAtPeriodEnd = $stripeData->cancel_at_period_end;
        $subscription->setCancelAtPeriodEnd($cancelAtPeriodEnd);

        if ($cancelAtPeriodEnd) {
            $cancelAt = \DateTimeImmutable::createFromFormat('U', (string) $stripeData->cancel_at);
            $canceledAt = \DateTimeImmutable::createFromFormat('U', (string) $stripeData->canceled_at);
            $subscription->setCancelAt($cancelAt instanceof \DateTimeImmutable ? $cancelAt : null);
            $subscription->setCanceledAt($canceledAt instanceof \DateTimeImmutable ? $canceledAt : null);
        } else {
            $subscription->setCancelAt(null);
            $subscription->setCanceledAt(null);
        }
    }
}
