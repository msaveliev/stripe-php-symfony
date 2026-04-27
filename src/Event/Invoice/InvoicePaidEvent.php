<?php

declare(strict_types=1);

namespace App\Event\Invoice;

use App\Event\WebhookEventAbstract;
use Stripe\Invoice;

class InvoicePaidEvent extends WebhookEventAbstract
{
    public function getObject(): Invoice
    {
        /* @var Invoice */
        return Invoice::constructFrom((array) $this->getData()['object']);
    }

    public function isProcessable(): bool
    {
        return true;
    }
}
