<?php

declare(strict_types=1);

namespace App\StateMachine\Context;

use App\Entity\StripeSubscription;
use App\Entity\User;
use App\Enum\SubscriptionStatus;
use App\Messenger\Message\StripeEventMessageInterface;
use Stripe\Subscription;

final readonly class SubscriptionTransitionContext
{
    public function __construct(
        public User $user,
        public StripeSubscription $subscription,
        public ?StripeEventMessageInterface $message = null,
        public ?SubscriptionStatus $previousState = null,
        public ?Subscription $stripeData = null,
    ) {
    }
}
