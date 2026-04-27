# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-04-27

Initial public release — a Symfony 8 / PHP 8.4 reference application for Stripe subscription webhook processing.

### Added

- **Stripe webhook pipeline** (`POST /webhooks/stripe`):
  - Signature verification via `Stripe\Webhook::constructEvent`.
  - Per-event `symfony/lock` mutex (30 s TTL) to serialize concurrent retries.
  - Redis-backed idempotency (72 h TTL) — duplicate events short-circuit with `200 OK`.
  - Fails closed with `500` when Redis is unreachable so Stripe retries instead of dropping events.
  - Single-source event registry via `stripe.webhook_event_map` in `config/packages/stripe.yaml`.
- **Two-stage event model** — synchronous Symfony `Event` dispatched in-request for cheap observers, then a strongly-typed `Message` handed to `symfony/messenger` for async work.
- **Async processing** over SQS FIFO (`symfony/amazon-sqs-messenger`) with a dedicated failure transport (`failed_stripe_events`).
- **Transport routing via marker interfaces** — `StripeEventSyncMessageInterface` and `StripeEventAsyncMessageInterface` select the transport; no default routing.
- **`ConsumeOnlyValidationMiddleware`** — re-runs `symfony/validator` on messages only during consumption (detected via `ReceivedStamp`), so handlers trust their validated contracts.
- **Hand-rolled subscription state machine** (`SubscriptionStateMachine`) with a `[fromState][event] => Transition` table, typed `Action\*` classes (grant/revoke access, set cancellation fields), and guard support.
- **`StripeApiClient`** (`App\Client`) — wraps Stripe SDK calls with `RetryHandler` and maps all `ApiErrorException` subtypes to a typed exception hierarchy:
  - `PaymentException` (base) → `StripeException` (abstract) → `ApiException`, `CardException`, `InvalidRequestException`, `NetworkException`, `RateLimitException`, `PaymentAuthenticationException`.
- **Factories** — `StripeSubscriptionFactory` and `StripePaymentFactory` normalize raw Stripe API objects into typed domain models.
- **Event subscribers** — `SubscriptionEventSubscriber` and `InvoiceEventSubscriber` handle the in-process sync events and dispatch the corresponding async messages.
- **Supported webhook events** (initial set):
  - `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.paused`, `customer.subscription.deleted`, `customer.subscription.trial_will_end`
  - `invoice.paid`, `invoice.payment_failed`
- **Doctrine entities** — `User` (ORM-mapped), `StripeSubscription`, and `StripePayment` with domain-logic helpers (`isTrial()`, `isActive()`, `isCancelled()`, `isPendingCancellation()`, etc.).
- **`SubscriptionRepository`** — `findOneBy` lookup of local subscriptions by `subscriptionId`.
- **Redis event logger** (`RedisEventLogger`) behind `EventLoggerInterface` for idempotency key persistence.
- **`RetryHandler`** — wraps Stripe API calls with configurable retry / back-off logic.
- **`ConsumeOnlyValidationMiddleware`** — validates messages on the consumer side only.
- **Custom validator constraint** — `EntityExists` for asserting entity existence in message payloads.
- **Local dev tooling** — `docker-compose.yaml` with PostgreSQL 16 and Redis 7, `.env.example` template.
- **QA tooling** — phpstan level 9 (zero errors), php-cs-fixer (`@Symfony` + `@Symfony:risky` + `declare_strict_types`), phpunit 13 scaffolding.
- **Composer scripts** — `composer cs-fix` (apply php-cs-fixer) and `composer cs-check` (dry-run diff) added to `composer.json`.

### Notes

- Stripe API version is injected from the `STRIPE_API_VERSION` env var into `Stripe\StripeClient` via `config/packages/stripe.yaml` — not hardcoded.
- Handler classes contain the subscription state-machine wiring; business-side effects (e.g. sending emails, provisioning access) are delegated to `Action\*` classes and can be extended there.

[Unreleased]: https://github.com/msaveliev/stripe-php-symfony/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/msaveliev/stripe-php-symfony/releases/tag/v1.0.0
