<?php

declare(strict_types=1);

namespace App\Event\Subscription;

use App\Event\WebhookEventAbstract;
use Stripe\Subscription;

class SubscriptionDeletedEvent extends WebhookEventAbstract
{
    public function getObject(): Subscription
    {
        /* @var Subscription */
        return Subscription::constructFrom((array) $this->getData()['object']);
    }

    public function isProcessable(): bool
    {
        return true;
    }
}
