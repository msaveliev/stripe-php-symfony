<?php

declare(strict_types=1);

namespace App\StateMachine;

use App\Enum\SubscriptionStatus;
use App\StateMachine\Context\SubscriptionTransitionContext;

final readonly class Transition
{
    /**
     * @param SubscriptionStatus $to      Target state
     * @param callable|null      $guard   Validation function
     * @param array<callable>    $actions Actions to execute synchronously
     */
    public function __construct(
        public SubscriptionStatus $to,
        public mixed $guard = null,
        public array $actions = [],
    ) {
    }

    public function isAllowed(SubscriptionTransitionContext $context): bool
    {
        if (null === $this->guard) {
            return true;
        }

        return ($this->guard)($context);
    }

    /**
     * Execute actions synchronously.
     */
    public function execute(SubscriptionTransitionContext $context): void
    {
        foreach ($this->actions as $action) {
            $action($context);
        }
    }
}
