<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\StripeSubscription;
use App\Entity\User;
use App\Enum\SubscriptionStatus;
use Stripe\Invoice;
use Stripe\Subscription;

final class StripeSubscriptionFactory
{
    public function createFromStripeApiSubscription(
        User $user,
        Subscription $stripeApiSubscription,
        SubscriptionStatus $status,
    ): StripeSubscription {
        $subscriptionItem = $stripeApiSubscription->items->first();
        if (null === $subscriptionItem) {
            throw new \RuntimeException('Stripe subscription has no items.');
        }
        $subscriptionPrice = $subscriptionItem->price;
        $subscriptionInvoice = $stripeApiSubscription->latest_invoice;

        $internalSubscription = new StripeSubscription();
        $internalSubscription->setUser($user);
        $internalSubscription->setStatus($status);
        $internalSubscription->setSubscriptionId($stripeApiSubscription->id);

        $currentPeriodStart = \DateTimeImmutable::createFromFormat('U', (string) $subscriptionItem->current_period_start);
        $currentPeriodEnd = \DateTimeImmutable::createFromFormat('U', (string) $subscriptionItem->current_period_end);
        if (false === $currentPeriodStart || false === $currentPeriodEnd) {
            throw new \RuntimeException('Failed to parse subscription period timestamps.');
        }
        $internalSubscription->setCurrentPeriodStart($currentPeriodStart);
        $internalSubscription->setCurrentPeriodEnd($currentPeriodEnd);

        if (Subscription::STATUS_TRIALING === $stripeApiSubscription->status) {
            $trialStart = \DateTimeImmutable::createFromFormat('U', (string) $stripeApiSubscription->trial_start);
            $trialEnd = \DateTimeImmutable::createFromFormat('U', (string) $stripeApiSubscription->trial_end);
            $internalSubscription->setTrialStart($trialStart instanceof \DateTimeImmutable ? $trialStart : null);
            $internalSubscription->setTrialEnd($trialEnd instanceof \DateTimeImmutable ? $trialEnd : null);
        }

        $internalSubscription->setPriceId($subscriptionPrice->id);

        if ($subscriptionInvoice instanceof Invoice) {
            $internalSubscription->setLatestInvoiceId($subscriptionInvoice->id);
        }

        $cancelAtPeriodEnd = $stripeApiSubscription->cancel_at_period_end;
        $internalSubscription->setCancelAtPeriodEnd($cancelAtPeriodEnd);
        if ($cancelAtPeriodEnd) {
            $cancelAt = \DateTimeImmutable::createFromFormat('U', (string) $stripeApiSubscription->cancel_at);
            $canceledAt = \DateTimeImmutable::createFromFormat('U', (string) $stripeApiSubscription->canceled_at);
            $internalSubscription->setCancelAt($cancelAt instanceof \DateTimeImmutable ? $cancelAt : null);
            $internalSubscription->setCanceledAt($canceledAt instanceof \DateTimeImmutable ? $canceledAt : null);
        }

        $endedAt = $stripeApiSubscription->ended_at;
        if (null !== $endedAt) {
            $parsedEndedAt = \DateTimeImmutable::createFromFormat('U', (string) $endedAt);
            if ($parsedEndedAt instanceof \DateTimeImmutable) {
                $internalSubscription->setEndedAt($parsedEndedAt);
            }
        }

        return $internalSubscription;
    }
}
