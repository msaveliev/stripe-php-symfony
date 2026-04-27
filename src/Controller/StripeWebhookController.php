<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidPayloadException;
use App\Exception\InvalidSignatureException;
use App\Service\EventLogger\EventLoggerInterface;
use App\Webhook\StripeWebhookVerifier;
use App\Webhook\WebhookEventFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeWebhookVerifier $webhookVerifier,
        private readonly WebhookEventFactory $eventFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventLoggerInterface $eventLogger,
        private readonly LockFactory $stripeEventsLockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/webhooks/stripe', name: 'stripe.webhook', methods: ['POST'])]
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');
        if (!$payload || !$signature) {
            return $this->json('Invalid request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = $this->webhookVerifier->verifyAndParse($payload, $signature);
        } catch (InvalidPayloadException|InvalidSignatureException $e) {
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Webhook error: '.$e->getMessage());

            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        ['eventId' => $eventId, 'objectId' => $objectId, 'type' => $type] = $event;

        if (!$this->eventFactory->supports($type)) {
            $this->logger->info('Ignoring unsupported webhook event: '.$type);

            return $this->json('OK');
        }

        $uniqueKey = $type.'_'.$objectId;
        $lock = $this->stripeEventsLockFactory->createLock($eventId, 30);
        try {
            if (!$lock->acquire()) {
                /* Another process is currently checking this event */
                return $this->json('Event already being processed.');
            }
        } catch (\Throwable $e) {
            $this->logger->critical('Error processing a webhook event.', [
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->json('Internal server error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            if ($this->eventLogger->isLogged($eventId)) {
                $this->logger->info('Duplicate webhook event ignored: '.$eventId);

                return $this->json('OK');
            }
        } catch (\Throwable $e) {
            $this->logger->critical('Redis cache unavailable during webhook processing.', [
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->json('Internal server error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $event = $this->eventFactory->create($eventId, $type, $event['data']);
            if (!$event->isProcessable()) {
                $this->logger->info('Ignoring non-processable webhook event: '.$type.', '.$eventId);

                return $this->json('OK');
            }

            $this->eventDispatcher->dispatch($event);
            $this->eventLogger->log($eventId);
        } catch (\Throwable $e) {
            $this->logger->critical('Error processing a webhook event.', [
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->json('Internal server error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            $lock->release();
        }

        $this->logger->info("Processed webhook event: $type, $eventId");

        return $this->json('OK');
    }
}
