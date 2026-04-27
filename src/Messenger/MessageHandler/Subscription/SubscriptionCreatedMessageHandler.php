<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Subscription;

use App\Messenger\Message\Subscription\SubscriptionCreatedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SubscriptionCreatedMessageHandler
{
    public function __invoke(SubscriptionCreatedMessage $message): void
    {
        // TODO: Handle customer.subscription.created event idempotently
    }
}
