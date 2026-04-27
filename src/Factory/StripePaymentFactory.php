<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\StripePayment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;

final class StripePaymentFactory
{
    public function createFromStripeApiInvoice(
        User $user,
        Invoice $stripeApiInvoice,
        ?PaymentMethod $paymentMethod = null,
    ): StripePayment {
        $internalPayment = new StripePayment();

        $paymentsCollection = $stripeApiInvoice->payments;
        if (null === $paymentsCollection) {
            throw new \RuntimeException('Expected expanded payments collection on Invoice.');
        }
        $invoicePayment = $paymentsCollection->first();
        if (null === $invoicePayment) {
            throw new \RuntimeException('Invoice has no payments.');
        }
        $payment = $invoicePayment->payment;
        $paymentIntent = $payment['payment_intent'];
        if (!$paymentIntent instanceof PaymentIntent) {
            throw new \RuntimeException('Expected expanded PaymentIntent on Invoice payment.');
        }

        $internalPayment->setPaymentIntentId(
            $paymentIntent->id
        );

        $latestCharge = $paymentIntent->latest_charge;
        if (\is_string($latestCharge) && '' !== $latestCharge) {
            $internalPayment->setChargeId($latestCharge);
        }

        $internalPayment->setStatus(PaymentStatus::from($paymentIntent->status));

        if ($paymentMethod instanceof PaymentMethod) {
            $internalPayment->setType($this->resolveType($paymentMethod));
        } else {
            $internalPayment->setType(PaymentMethod::TYPE_CARD);
        }

        $internalPayment->setUser($user);

        $lastError = $paymentIntent->last_payment_error?->toArray() ?? [];
        if ([] !== $lastError) {
            $internalPayment->setFailureCode($lastError['code'] ?? null);
            $internalPayment->setFailureMessage($lastError['message'] ?? null);
        }

        return $internalPayment;
    }

    private function resolveType(PaymentMethod $paymentMethod): string
    {
        $baseType = $paymentMethod->type;
        $card = $paymentMethod->card;

        if (PaymentMethod::TYPE_CARD === $baseType && null !== $card && null !== $card->wallet?->type) {
            return $card->wallet->type;
        }

        return $baseType;
    }
}
