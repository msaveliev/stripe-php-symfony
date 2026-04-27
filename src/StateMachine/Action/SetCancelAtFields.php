<?php

declare(strict_types=1);

namespace App\StateMachine\Action;

use App\StateMachine\Context\SubscriptionTransitionContext;

final readonly class SetCancelAtFields
{
    public function __invoke(SubscriptionTransitionContext $context): void
    {
        $subscription = $context->subscription;
        $stripeData = $context->stripeData;

        if (null === $stripeData) {
            return;
        }

        $cancelAt = $stripeData->cancel_at;

        if (null !== $cancelAt) {
            $parsed = \DateTimeImmutable::createFromFormat('U', (string) $cancelAt);
            $subscription->setCancelAt($parsed instanceof \DateTimeImmutable ? $parsed : null);
        } else {
            $subscription->setCancelAt(null);
        }
    }
}
