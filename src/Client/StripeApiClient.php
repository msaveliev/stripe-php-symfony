<?php

declare(strict_types=1);

namespace App\Client;

use App\Exception\ApiException;
use App\Exception\CardException;
use App\Exception\InvalidRequestException;
use App\Exception\NetworkException;
use App\Exception\PaymentAuthenticationException;
use App\Exception\RateLimitException;
use App\Exception\StripeException;
use App\Service\RetryHandler;
use Psr\Log\LoggerInterface;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Exception\RateLimitException as StripeRateLimitException;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Subscription;
use Symfony\Component\HttpFoundation\Response;

final readonly class StripeApiClient
{
    public function __construct(
        private RetryHandler $retryHandler,
        private StripeClient $client,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $expand
     *
     * @throws StripeException
     */
    public function getSubscriptionById(string $subscriptionId, array $expand = []): Subscription
    {
        return $this->retryHandler->execute(function () use ($subscriptionId, $expand) {
            try {
                $params = [];

                if (\count($expand) > 0) {
                    $params['expand'] = $expand;
                }

                return $this->client->subscriptions->retrieve($subscriptionId, $params);
            } catch (ApiErrorException $e) {
                throw $this->handleStripeException($e);
            }
        });
    }

    /**
     * @param list<string> $expand
     *
     * @throws StripeException
     */
    public function getInvoiceById(string $invoiceId, array $expand = []): Invoice
    {
        return $this->retryHandler->execute(function () use ($invoiceId, $expand) {
            try {
                $params = [];

                if (\count($expand) > 0) {
                    $params['expand'] = $expand;
                }

                return $this->client->invoices->retrieve($invoiceId, $params);
            } catch (ApiErrorException $e) {
                throw $this->handleStripeException($e);
            }
        });
    }

    /**
     * @throws StripeException
     */
    public function getCustomer(string $customerId): Customer
    {
        return $this->retryHandler->execute(function () use ($customerId) {
            try {
                return $this->client->customers->retrieve($customerId);
            } catch (ApiErrorException $e) {
                throw $this->handleStripeException($e);
            }
        });
    }

    private function handleStripeException(ApiErrorException $e): StripeException
    {
        $errorCode = $e->getStripeCode();
        $errorType = $e->getError()?->type;
        $requestId = $e->getRequestId();
        $httpStatus = $e->getHttpStatus();

        $this->logger->error('Stripe API error', [
            'error_code' => $errorCode,
            'error_type' => $errorType,
            'request_id' => $requestId,
            'http_status' => $httpStatus,
            'message' => $e->getMessage(),
        ]);

        return match (true) {
            $e instanceof ApiConnectionException => new NetworkException(
                message: 'Failed to connect to Stripe API',
                requestId: $requestId,
                previous: $e
            ),
            $e instanceof StripeRateLimitException => new RateLimitException(
                message: 'Stripe rate limit exceeded',
                requestId: $requestId,
                retryAfter: (int) ($e->getHttpHeaders()['Retry-After'] ?? 60),
                previous: $e
            ),
            $e instanceof AuthenticationException => new PaymentAuthenticationException(
                message: 'Stripe authentication failed - check API key',
                requestId: $requestId,
                previous: $e
            ),
            $e instanceof StripeInvalidRequestException => new InvalidRequestException(
                message: $e->getMessage(),
                requestId: $requestId,
                errorCode: $errorCode,
                param: $e->getError()?->param,
                previous: $e
            ),
            $e instanceof StripeCardException => new CardException(
                message: $e->getMessage(),
                requestId: $requestId,
                declineCode: $e->getDeclineCode(),
                previous: $e
            ),
            default => new ApiException(
                message: $e->getMessage(),
                requestId: $requestId,
                errorCode: $errorCode,
                httpStatus: $httpStatus,
                retryable: \in_array($httpStatus, [
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    Response::HTTP_BAD_GATEWAY,
                    Response::HTTP_SERVICE_UNAVAILABLE,
                    Response::HTTP_GATEWAY_TIMEOUT,
                ]),
                previous: $e
            ),
        };
    }
}
