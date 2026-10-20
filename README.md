# LinkPilot

LinkPilot is a Laravel and React publishing console for owned short links and publishable link-in-bio pages.

## Included workflows

- Create, edit, pause, delete, and inspect owned short links.
- Group owned short links in private folders.
- Schedule link availability and require an access code before a public redirect.
- Download locally generated SVG QR codes for owned short links.
- Follow an active short URL and record one privacy-minimal click event.
- Create multiple bio pages, order named outbound buttons, and publish only active buttons at `/bio/{handle}`.
- Review local account plan limits and subscription usage at `/billing`; this is internal entitlement state, not payment collection.

## QR codes

The authenticated QR dashboard serves each owned link through
`GET /links/{link}/qr-code.svg`. LinkPilot generates SVG bytes locally with
Endroid QR Code; it does not send the destination or QR image through an
external image service. Each QR code encodes the absolute public short-link
URL, so normal availability and access-code controls still apply after a scan.

## Account plans and local billing

Every account receives a local free subscription after migration or registration. The billing page reports usage for short links, custom domains, tracking pixels, link pages, and webhooks and enforces their plan limits. No Stripe, PayPal, checkout form, invoices, taxes, or payment processing are configured in this repository.

## Administration

Grant administrator access only to an existing local account:

```bash
docker compose run --rm app php artisan linkpilot:grant-admin user@example.test
```

`/admin` exposes account metadata, subscription state, and aggregate ownership
counts; it never lists private content, destinations, visitor data, or secrets.
Suspending an account clears its database sessions and remember token while
leaving its already-public content available.

## Webhooks

Each account can register HTTPS endpoints that receive consented analytics
events for that account. `Pro` accounts may keep five webhooks; the `Free`
plan allows none.

Deliveries are queued, so a worker must be running:

```bash
docker compose up app queue
```

Every delivery is a JSON `POST` signed with HMAC-SHA256 over the raw body:

```bash
X-LinkPilot-Signature: sha256=<hex digest>
X-LinkPilot-Event: link.clicked
X-LinkPilot-Timestamp: 2026-01-01T00:00:00Z
User-Agent: LinkPilot-Webhooks/1.0
```

LinkPilot signs with the secret printed once when a webhook is created or its
secret is rotated; secrets are stored encrypted and never shown again. Endpoints
must be public HTTPS URLs without embedded credentials, and non-public
addresses (loopback, private, link-local, reserved) are rejected and re-checked
before every attempt, so deliveries never reach an internal host. Failed
deliveries retry with the delays in `WEBHOOK_RETRY_DELAYS` before the delivery
is marked failed; the retry schedule and timeouts live under `services.linkpilot.webhooks`.

## Requirements

- Docker Engine with Docker Compose v2.

The default local database is SQLite at `database/database.sqlite`. The app is served at [http://localhost:8000](http://localhost:8000).

## First run

```bash
cp .env.example .env
touch database/database.sqlite
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app npm install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm app npm run build
docker compose up --build
```

Keep the final command running while using the application, and run the queue
worker alongside it so queued webhook deliveries are attempted:

```bash
docker compose up app queue
```

In another terminal, rerun migrations after pulling schema changes:

```bash
docker compose run --rm app php artisan migrate
```

Local email verification uses the log mailer. After registering, open the verification URL written to `storage/logs/laravel.log`.

## Verification commands

```bash
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

## Production asset rebuild

After changing React or CSS source, rebuild the Vite manifest:

```bash
docker compose run --rm app npm run build
```
