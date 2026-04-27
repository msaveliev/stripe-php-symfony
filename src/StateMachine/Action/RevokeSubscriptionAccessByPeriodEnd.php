<?php

declare(strict_types=1);

namespace App\StateMachine\Action;

use App\StateMachine\Context\SubscriptionTransitionContext;

final readonly class RevokeSubscriptionAccessByPeriodEnd
{
    public function __invoke(SubscriptionTransitionContext $context): void
    {
        // TODO: Handle revoke access by period end
    }
}
