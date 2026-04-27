<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Invoice;

use App\Messenger\Message\Invoice\InvoicePaidMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class InvoicePaidMessageHandler
{
    public function __invoke(InvoicePaidMessage $message): void
    {
        // TODO: Handle invoice.paid event idempotently
    }
}
