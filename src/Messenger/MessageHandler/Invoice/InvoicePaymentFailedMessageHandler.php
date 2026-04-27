<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Invoice;

use App\Client\StripeApiClient;
use App\Exception\StripeException;
use App\Messenger\Message\Invoice\InvoicePaymentFailedMessage;
use Stripe\Invoice;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class InvoicePaymentFailedMessageHandler
{
    public function __construct(
        private StripeApiClient $stripeProvider,
    ) {
    }

    public function __invoke(InvoicePaymentFailedMessage $message): void
    {
        try {
            $stripeApiInvoice = $this->stripeProvider->getInvoiceById($message->getInvoiceId(), ['payments.data.payment.payment_intent']);
        } catch (StripeException $e) {
            if ($e->retryable) {
                throw $e;
            }
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('Invoice retrieval from Stripe API failed', 0, $e);
        }

        if (Invoice::STATUS_PAID === $stripeApiInvoice->status) {
            return;
        }

        // TODO: Handle invoice.payment_failed event
    }
}
