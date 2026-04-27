<?php

declare(strict_types=1);

namespace App\Trait;

use App\Entity\StripeSubscription;
use App\Enum\SubscriptionStatus;
use Doctrine\Common\Collections\Collection;

// @phpstan-ignore-next-line trait.unused
trait UseStripeSubscription
{
    /**
     * @ORM\OneToMany(targetEntity=StripeSubscription::class, mappedBy="user", orphanRemoval=true)
     *
     * @ORM\OrderBy({"currentPeriodEnd": "DESC"})
     *
     * @var Collection<int, StripeSubscription>
     */
    private Collection $stripeSubscriptions;

    /**
     * @return Collection<int, StripeSubscription>
     */
    public function getStripeSubscriptions(): Collection
    {
        return $this->stripeSubscriptions;
    }

    public function addStripeSubscription(StripeSubscription $subscription): self
    {
        if (!$this->stripeSubscriptions->contains($subscription)) {
            $this->stripeSubscriptions[] = $subscription;
            $subscription->setUser($this);
        }

        return $this;
    }

    public function removeStripeSubscription(StripeSubscription $subscription): self
    {
        if ($this->stripeSubscriptions->contains($subscription)) {
            $this->stripeSubscriptions->removeElement($subscription);
            // set the owning side to null (unless already changed)
            if ($subscription->getUser() === $this) {
                $subscription->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Check if a Stripe subscription status is considered "active".
     */
    private function isStripeSubscriptionActive(StripeSubscription $subscription): bool
    {
        $activeStatuses = [
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::TRIALING->value,
            SubscriptionStatus::PAST_DUE->value,
        ];

        return \in_array($subscription->getStatus(), $activeStatuses, true)
            && $subscription->getCurrentPeriodEnd() > new \DateTime();
    }

    /**
     * Check if a user has any valid Stripe subscription
     * Similar to isSubscriptionValid() but for Stripe.
     */
    public function isStripeSubscriptionValid(): bool
    {
        $first = $this->stripeSubscriptions->first();

        return $first instanceof StripeSubscription && $this->isStripeSubscriptionActive($first);
    }

    /**
     * Check if a user has any Stripe subscription.
     */
    public function hasAnyStripeSubscription(): bool
    {
        return $this->stripeSubscriptions->count() > 0;
    }

    public function getLatestStripeSubscription(): ?StripeSubscription
    {
        $first = $this->stripeSubscriptions->first();

        return $first instanceof StripeSubscription ? $first : null;
    }

    /**
     * Check if a user has any active Stripe subscription.
     */
    public function hasActiveStripeSubscription(): bool
    {
        return null !== $this->getActiveStripeSubscription();
    }

    /**
     * Get the latest active Stripe subscription.
     */
    public function getActiveStripeSubscription(): ?StripeSubscription
    {
        foreach ($this->stripeSubscriptions as $subscription) {
            if ($this->isStripeSubscriptionActive($subscription)) {
                return $subscription;
            }
        }

        return null;
    }
}
