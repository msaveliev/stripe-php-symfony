<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionStatus;

class StripeSubscription
{
    private ?int $id = null; // @phpstan-ignore property.unusedType

    private ?User $user = null;

    private string $status;

    private string $subscriptionId;

    private string $priceId;

    private ?string $latestInvoiceId = null;

    private \DateTimeInterface $currentPeriodStart;

    private \DateTimeInterface $currentPeriodEnd;

    private ?\DateTimeInterface $trialStart = null;

    private ?\DateTimeInterface $trialEnd = null;

    private bool $cancelAtPeriodEnd = false;

    private ?\DateTimeInterface $cancelAt = null;

    private ?\DateTimeInterface $canceledAt = null;

    private ?\DateTimeImmutable $endedAt = null;

    private \DateTimeImmutable $createdAt;

    private \DateTimeInterface $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        if (null === $this->user) {
            throw new \LogicException('User is not set on StripeSubscription');
        }

        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatus $status): static
    {
        $this->status = $status->value;

        return $this;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(string $subscriptionId): static
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    public function getPriceId(): string
    {
        return $this->priceId;
    }

    public function setPriceId(string $priceId): static
    {
        $this->priceId = $priceId;

        return $this;
    }

    public function getLatestInvoiceId(): ?string
    {
        return $this->latestInvoiceId;
    }

    public function setLatestInvoiceId(?string $latestInvoiceId): static
    {
        $this->latestInvoiceId = $latestInvoiceId;

        return $this;
    }

    public function getCurrentPeriodStart(): \DateTimeInterface
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(\DateTimeInterface $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;

        return $this;
    }

    public function getCurrentPeriodEnd(): \DateTimeInterface
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(\DateTimeInterface $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;

        return $this;
    }

    public function getTrialStart(): ?\DateTimeInterface
    {
        return $this->trialStart;
    }

    public function setTrialStart(?\DateTimeInterface $trialStart): static
    {
        $this->trialStart = $trialStart;

        return $this;
    }

    public function getTrialEnd(): ?\DateTimeInterface
    {
        return $this->trialEnd;
    }

    public function setTrialEnd(?\DateTimeInterface $trialEnd): static
    {
        $this->trialEnd = $trialEnd;

        return $this;
    }

    public function getCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(bool $cancelAtPeriodEnd): static
    {
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;

        return $this;
    }

    public function getCancelAt(): ?\DateTimeInterface
    {
        return $this->cancelAt;
    }

    public function setCancelAt(?\DateTimeInterface $cancelAt): static
    {
        $this->cancelAt = $cancelAt;

        return $this;
    }

    public function getCanceledAt(): ?\DateTimeInterface
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeInterface $canceledAt): static
    {
        $this->canceledAt = $canceledAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Whether the subscription is currently in an active trial period.
     * Status is TRIALING, trialEnd is set, and trialEnd is in the future.
     */
    public function isTrial(): bool
    {
        return $this->getStatus() === SubscriptionStatus::TRIALING->value
            && null !== $this->getTrialEnd()
            && $this->getTrialEnd() > new \DateTimeImmutable();
    }

    /**
     * Whether the subscription is currently active (ACTIVE, TRIALING, or PAST_DUE),
     * and the current period has not yet ended.
     */
    public function isActive(): bool
    {
        return \in_array($this->getStatus(), [
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::TRIALING->value,
            SubscriptionStatus::PAST_DUE->value,
        ])
        && $this->getCurrentPeriodEnd() > new \DateTimeImmutable();
    }

    /**
     * Whether the subscription has been canceled (status is CANCELED and canceledAt is set).
     */
    public function isCancelled(): bool
    {
        return $this->getStatus() === SubscriptionStatus::CANCELED->value
            && null !== $this->getCanceledAt();
    }

    /**
     * Subscription is past_due — payment failed but still in grace/retry period.
     */
    public function isInGracePeriod(): bool
    {
        return $this->getStatus() === SubscriptionStatus::PAST_DUE->value;
    }

    /**
     * Subscription is scheduled to cancel at the period end but still active.
     */
    public function isPendingCancellation(): bool
    {
        return $this->cancelAtPeriodEnd
            && $this->getStatus() !== SubscriptionStatus::CANCELED->value;
    }

    /**
     * Subscription is scheduled to cancel at a custom date (not at period end).
     * cancel_at is set, cancel_at_period_end is false, and status is not yet CANCELED.
     */
    public function isPendingCustomDateCancellation(): bool
    {
        return null !== $this->cancelAt
            && !$this->cancelAtPeriodEnd
            && $this->getStatus() !== SubscriptionStatus::CANCELED->value;
    }

    public function isEnded(): bool
    {
        return null !== $this->endedAt;
    }
}
