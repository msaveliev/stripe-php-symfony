# Stripe Subscription Integration for Symfony

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-777bb4)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/symfony-8.0-000000)](https://symfony.com/)
[![Stripe PHP](https://img.shields.io/badge/stripe--php-%5E20.0-635bff)](https://github.com/stripe/stripe-php)

A reference Symfony 8 application demonstrating a **production-shape Stripe subscription webhook pipeline**: signature verification, idempotency, per-event locking, asynchronous processing through SQS FIFO, and a hand-rolled subscription state machine.

The app is deliberately small enough to read end-to-end while still showing the patterns that tend to bite in real Stripe integrations (duplicate webhooks, retries, trial-to-paid transitions, FIFO group ordering across message redeliveries, etc.).

## Features

- **Webhook pipeline** (`POST /webhooks/stripe`):
  - Signature verification via `Stripe\Webhook::constructEvent`.
  - Per-event `symfony/lock` mutex to serialize concurrent retries (30 s TTL).
  - Redis-backed idempotency (72 h TTL) — duplicate events short-circuit with `200 OK`.
  - Fails closed (returns `500`) when Redis is unreachable so Stripe retries instead of dropping events.
  - Single-source event registry via `stripe.webhook_event_map` in `config/packages/stripe.yaml`.
- **Two-stage event model** — a synchronous Symfony `Event` is dispatched inside the webhook request for cheap in-process observers; a strongly-typed `Message` is then handed to `symfony/messenger` for async work.
- **Async processing** over SQS FIFO (`symfony/amazon-sqs-messenger`) with a dedicated failure transport (`failed_stripe_events`).
- **Transport routing via marker interfaces** — `StripeEventSyncMessageInterface` and `StripeEventAsyncMessageInterface` select the transport without default routing.
- **`ConsumeOnlyValidationMiddleware`** — re-runs `symfony/validator` on messages only during consumption (detected via `ReceivedStamp`), letting handlers trust their contracts.
- **Subscription state machine** (`SubscriptionStateMachine`) — transitions between `trialing` / `active` / `past_due` / `canceled` / ... are declared in one `[fromState][event] => Transition` table with typed `Action\*` classes (grant/revoke access, set cancellation fields) and guard support.
- **`StripeApiClient`** — wraps every Stripe SDK call with retry logic (`RetryHandler`) and maps all `ApiErrorException` subtypes to a typed exception hierarchy (`StripeException` → `CardException`, `NetworkException`, `RateLimitException`, etc.).
- **Factories** (`StripeSubscriptionFactory`, `StripePaymentFactory`) — normalize raw Stripe API objects into typed domain models.
- **Redis event logger** — records processed event IDs for idempotency enforcement.

## Supported webhook events

| Stripe event | Handler |
|---|---|
| `customer.subscription.created` | `SubscriptionCreatedMessageHandler` |
| `customer.subscription.updated` | `SubscriptionUpdatedMessageHandler` |
| `customer.subscription.paused` | `SubscriptionPausedMessageHandler` |
| `customer.subscription.deleted` | `SubscriptionDeletedMessageHandler` |
| `customer.subscription.trial_will_end` | `SubscriptionTrialEndingMessageHandler` |
| `invoice.paid` | `InvoicePaidMessageHandler` |
| `invoice.payment_failed` | `InvoicePaymentFailedMessageHandler` |

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Symfony 8.0 (`framework-bundle`, `security-bundle`) |
| Payments | `stripe/stripe-php` ^20 (API version pinned via `STRIPE_API_VERSION` env) |
| Persistence | Doctrine ORM ^3.6 + PostgreSQL 16 |
| Async | `symfony/messenger` + `symfony/amazon-sqs-messenger` (FIFO queue + failure transport) |
| Cache / idempotency | `symfony/cache` + `predis/predis` → Redis |
| Locking | `symfony/lock` |
| QA | phpstan (level 9), php-cs-fixer (`@Symfony:risky`), phpunit 13 |

## Getting started

### Prerequisites

- PHP 8.4
- Composer
- Docker + Docker Compose (for the bundled PostgreSQL / Redis services), **or** your own PostgreSQL 16 and Redis instances
- An AWS account with two SQS **FIFO** queues (one primary, one for failures), or an SQS-compatible local service
- A Stripe account (test mode is fine)

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

Copy the example env file and fill in the placeholders:

```bash
cp .env.example .env.local
```

| Variable | Purpose |
|---|---|
| `APP_SECRET` | Symfony app secret. |
| `DATABASE_URL` | PostgreSQL DSN. |
| `LOCK_DSN` | `flock` for local dev; a shared store (Redis / Postgres) in prod. |
| `STRIPE_API_VERSION` | Stripe API version string (e.g. `2026-02-25.clover`). |
| `STRIPE_SECRET_KEY` | Stripe secret key. |
| `STRIPE_WEBHOOK_SECRET` | Signing secret of the webhook endpoint. |
| `MESSENGER_TRANSPORT_DSN` | Generic async transport DSN. |
| `MESSENGER_STRIPE_TRANSPORT_DSN` | SQS FIFO DSN for Stripe events. |
| `MESSENGER_STRIPE_FAILED_TRANSPORT_DSN` | SQS FIFO DSN for failed Stripe events. |

Redis is expected at `redis://localhost` (see `config/packages/cache.yaml`).

### 3. Start PostgreSQL and Redis

```bash
docker compose up -d
```

Override defaults by exporting `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`, `POSTGRES_PORT`, or `REDIS_PORT` before `docker compose up`.

### 4. Create the database schema

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Run the webhook worker

The webhook endpoint returns `200 OK` immediately after persisting and dispatching the event. Business logic runs in the worker:

```bash
php bin/console messenger:consume stripe_events_async -vv
```

Retry failed events from the failure transport:

```bash
php bin/console messenger:consume failed_stripe_events -vv
```

### 6. Point Stripe at your webhook

Expose `POST /webhooks/stripe` and copy the signing secret into `STRIPE_WEBHOOK_SECRET`:

```bash
stripe listen --forward-to http://localhost:8000/webhooks/stripe
```

## HTTP endpoints

| Method | Route | Purpose |
|---|---|---|
| `POST` | `/webhooks/stripe` | Stripe webhook receiver. |

## Quality tooling

```bash
php -d memory_limit=512M vendor/bin/phpstan analyse  # static analysis, level 9
composer cs-check                                    # code style dry-run (diff)
composer cs-fix                                      # apply code style fixes
vendor/bin/phpunit                                   # test suite
php bin/console lint:container                       # DI wiring validation
```

## Extending

### Adding a new webhook event

1. Add an entry to `stripe.webhook_event_map` in `config/packages/stripe.yaml`.
2. Create the paired classes:
   - `src/Event/<Domain>/<X>Event.php` extending `WebhookEventAbstract`.
   - A listener method in the matching `src/EventSubscriber/<Domain>EventSubscriber.php`.
   - `src/Message/<Domain>/<X>Message.php` implementing `StripeEventSyncMessageInterface` or `StripeEventAsyncMessageInterface`.
   - `src/MessageHandler/<Domain>/<X>MessageHandler.php` with `#[AsMessageHandler]`.
3. When dispatching an async `Message`, attach both `AmazonSqsFifoStamp` and `SqsFifoGroupStamp` with the same group id — see `SubscriptionEventSubscriber::onSubscriptionCreated` for the reference shape.

### Adding a subscription state transition

Edit `SubscriptionStateMachine::buildTransitions()` and `determineEvent()`; add any new `StateMachine/Action/*` classes (they are autowired).

## Getting help

- **Stripe docs**: <https://stripe.com/docs/api> and <https://stripe.com/docs/webhooks>
- **Symfony docs**: <https://symfony.com/doc/current/index.html> — especially the Messenger and Lock components
- **Issues / bugs**: file an issue on the project's GitHub repository

## License

Released under the [MIT License](LICENSE).
