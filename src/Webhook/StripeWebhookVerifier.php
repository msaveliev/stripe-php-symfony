<?php

declare(strict_types=1);

namespace App\Webhook;

use App\Exception\InvalidPayloadException;
use App\Exception\InvalidSignatureException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

final readonly class StripeWebhookVerifier
{
    public function __construct(
        private string $webhookSecret,
    ) {
    }

    /**
     * @return array{eventId: string, objectId: string, type: string, data: array<string, mixed>}
     *
     * @throws InvalidPayloadException
     * @throws InvalidSignatureException
     */
    public function verifyAndParse(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            $objectId = $event->data->object->id;

            if (null === $objectId) {
                throw new InvalidPayloadException('Webhook object is missing an id.', [], $payload);
            }

            return [
                'eventId' => $event->id,
                'objectId' => $objectId,
                'type' => $event->type,
                'data' => $event->data->toArray(),
            ];
        } catch (UnexpectedValueException $e) {
            throw new InvalidPayloadException('Webhook payload is invalid or malformed.', [], $payload, $e);
        } catch (SignatureVerificationException $e) {
            throw new InvalidSignatureException('Webhook signature verification failed.', $signature, $e);
        }
    }
}
