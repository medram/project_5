# LinkPilot

LinkPilot is a Laravel and React publishing console for owned short links and publishable link-in-bio pages.

## Included workflows

- Create, edit, pause, delete, and inspect owned short links.
- Group owned short links in private folders.
- Schedule link availability and require an access code before a public redirect.
- Create direct-URL or owned-link QR campaigns, control delivery, and download SVG, PNG, JPEG, or WebP.
- Follow an active short URL and record one privacy-minimal click event.
- Create multiple bio pages, order named outbound buttons, and publish only active buttons at `/bio/{handle}`.
- Publish branded link pages with managed destination items at `/pages/{slug}`.
- Serve an active CTA overlay as an interstitial before a short link redirects.
- Verify owned custom domains and route short links through the resolved host.
- Attach fixed Meta Pixel or GA4 integrations to published pages after consent.
- Review consent-gated account analytics and per-link insights.
- Register signed webhook endpoints for consented events with retry handling.
- Review local account plan limits and subscription usage at `/account-settings/billing`; this is internal entitlement state, not payment collection.
- Administer account status, administrator access, and local entitlements at `/admin`.

## QR campaigns

The authenticated QR dashboard at `/dashboard/qr-codes` manages persistent campaigns.
Campaigns target either an HTTP(S) URL or a link in the active workspace. Active,
schedule, access-code, and maximum-scan controls run before a scan is recorded.
Share metadata, allowlisted visual styles, and private logos are campaign
configuration. Each existing and newly created short link also has one derived
QR at `GET /dashboard/links/{link}/qr-code.svg`; that legacy asset encodes the
link’s canonical public URL. Persisted campaign downloads encode `/q/{code}` so
QR controls and scan analytics cannot be bypassed. Linked campaigns record one
QR scan and defer final link checks and click recording to the short-link route.
Historical link clicks are never backfilled as QR scans.

New campaigns include a live style preview. It encodes a non-live preview URL;
the scan-ready campaign URL is created only after saving.

SVG, PNG, JPEG, and WebP assets are generated locally. The PHP image requires GD
with PNG, JPEG, and WebP support; Composer declares `ext-gd`. Uploaded logos stay
on Laravel’s private local disk at `storage/app/private/qr-logos/` and are never
accepted as public storage paths. Production deployments must configure a durable
private disk or mount for that path and verify persistence across restarts; the
development Compose bind mount is not a production durability guarantee.

## Plans, Stripe billing, and workspace usage

Every workspace starts on Free and the effective plan resolves, in order: a current administrator override, a valid Stripe subscription mapped by its price history, then Free. The initial Free/Pro/Business catalog is seeded by the Phase 8 migration; administrators can create plans and adjust their quotas without deleting existing content. One locked service enforces count and retained-byte limits.

Stripe is the only paid path. `/account-settings/billing` shows the effective plan, all usage metrics, provider state, and a setup checklist; `/pricing` is public. Owner-only routes `/billing/checkout/{plan}/{interval}` and `/billing/portal` stay disabled until every prerequisite is configured. Cashier's signed webhook at `/stripe/webhook` is the only source of subscription activation—return URLs never grant a plan, and LinkPilot never edits Stripe status locally.

Required operator configuration:

```dotenv
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
CASHIER_TAX_POLICY_ACKNOWLEDGED=true
CASHIER_CURRENCY=usd
BILLING_PROMOTION_CODES=false
```

In `/admin/plans`, use **Verify & sync** for each monthly or yearly Stripe price, then point the webhook endpoint at `/stripe/webhook`. A legacy price ID is unverified until synchronized: existing subscriptions keep their entitlements, but its public price and new checkout remain unavailable. Previous verified prices stay mapped to their subscriptions when the current selection changes. Without Stripe credentials, synchronization and checkout stay unavailable; plan metadata and quota edits still work.

Optional Google sign-in is hidden unless `GOOGLE_AUTH_ENABLED=true` and both Google OAuth credentials are set. Existing email addresses are never auto-linked; connect Google from account settings while signed in.

## Local test accounts

Seed the local accounts used for manual testing:

```bash
docker compose -f compose.yaml -f compose.dev.yaml run --rm app php artisan db:seed --force
```

- `user@linkpilot.test` — owner on the `pro` plan with one of every module's
  content: folders, short links, a pending custom domain, a tracking pixel, a
  published bio page and link page, an active CTA overlay, a signed webhook, and
  click history.
- `admin@linkpilot.test` — the account to grant platform administrator access to
  with `linkpilot:grant-admin` (see below). Seeding never sets `is_admin`.

The seeder converges both accounts' credentials and plans on every run and
creates the content only on first run, so re-seeding never duplicates records.
The local password for both accounts is supplied in the run handoff rather than
stored in this repository.

## Environment configuration

| Variable                                                                                       | Purpose                                                                                                                            |
| ---------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `LINKPILOT_DEFAULT_HOSTS`                                                                      | Comma-separated hostname aliases the app answers on, beyond the canonical host in `APP_URL`.                                       |
| `LINKPILOT_COUNTRY_HEADER`                                                                     | Optional country-code header set by the trusted country proxy.                                                                     |
| `LINKPILOT_COUNTRY_PROXY_CIDRS`                                                                | Comma-separated proxy CIDRs matched against the socket peer address (`REMOTE_ADDR`).                                               |
| `PUBLIC_PRIVACY_URL`                                                                           | Privacy-policy link for public consent; required before production lead capture (lead consent is separate from analytics consent). |
| `LINKPILOT_MEDIA_DISK`                                                                         | Laravel disk for workspace media; configure durable private storage for production (no direct public disk URLs).                   |
| `CONTENT_MEDIA_RETENTION_DAYS`                                                                 | Active-media lifetime before daily pruning (365 days by default).                                                                  |
| `CLAMSCAN_PATH`                                                                                | ClamAV executable used to scan PDFs; upload fails closed if scanning is unavailable or unsuccessful.                               |
| `ANALYTICS_HASH_KEY`, `ANALYTICS_HASH_KEY_VERSION`                                             | Secret and version label used to hash visitor identifiers; rotate the key and bump the version together.                           |
| `ANALYTICS_GEOIP_DATABASE`                                                                     | Absolute path to an operator-provided local MaxMind `.mmdb`; unavailable files disable country lookup.                             |
| `ANALYTICS_RETENTION_DAYS`                                                                     | Retention window for consent-gated analytics events before `linkpilot:prune-analytics` removes them.                               |
| `WEBHOOK_RETRY_DELAYS`                                                                         | Comma-separated retry delays in seconds for failed webhook deliveries (`60,300` by default).                                       |
| `WEBHOOK_CONNECT_TIMEOUT`, `WEBHOOK_TIMEOUT`                                                   | Connection and total timeouts for a delivery attempt.                                                                              |
| `WEBHOOK_MAX_ATTEMPTS`                                                                         | Attempts per delivery before it is marked failed.                                                                                  |
| `WEBHOOK_EXCERPT_BYTES`                                                                        | Response bytes retained as a non-sensitive delivery excerpt.                                                                       |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Standard Laravel mail transport; local default writes mail to the log.                                                             |

Country targeting stays disabled when either country setting is blank. Configure
it only when a trusted proxy overwrites the selected header and connects directly
from the listed CIDRs. LinkPilot checks the socket peer address; forwarded client
address headers never grant trust. An untrusted or invalid country is treated as
unknown and uses the link's default target.

`ANALYTICS_GEOIP_DATABASE` points to a local MaxMind GeoLite2 Country `.mmdb`
file. Obtain and update the database under its applicable license, mount it
read-only into the application and worker, and do not commit the database or
license credentials. LinkPilot performs no remote GeoIP lookup. An empty,
unreadable, or corrupt file leaves country results unavailable.

`APP_URL` is the canonical origin used in password-reset links. Normal web
routes answer only on that host and `LINKPILOT_DEFAULT_HOSTS`; a claimed custom
domain is limited to its domain challenge and public short-link routes.

Custom domains are verified with a DNS TXT record (`_linkpilot-verify.<host>`)
or an HTTPS challenge document served at
`/.well-known/linkpilot-domain/<token>`, then a TLS check. The scheduler runs
`linkpilot:verify-custom-domains`, `linkpilot:prune-analytics`,
`linkpilot:prune-content-media`, and `linkpilot:prune-link-page-leads` daily.
Run Laravel's scheduler every minute in production (for example, cron
`schedule:run`) so these maintenance jobs execute. Each can also be run manually.

Content media uses `LINKPILOT_MEDIA_DISK` and must remain on durable private
storage; eligible published blocks are delivered through LinkPilot routes.
`CONTENT_MEDIA_RETENTION_DAYS` defaults to 365 days, after which active media is
permanently pruned. PDF uploads fail closed unless the executable configured by
`CLAMSCAN_PATH` completes a clean ClamAV scan. Production lead capture requires
`PUBLIC_PRIVACY_URL`; lead-collection consent is distinct from analytics
tracking consent, and accepted lead values are permanently purged after 30 days.
Tracking pixels are limited to fixed Meta Pixel and GA4 integrations — arbitrary
third-party scripts are not accepted. Resources are scoped to the active
workspace. Workspace membership and invitations control access; the workspace
owner and administrators retain their existing management authority.

## Administration

Grant administrator access only to an existing local account:

```bash
docker compose run --rm app php artisan linkpilot:grant-admin user@example.test
```

The content inventories at `/admin` expose metadata only. A separately granted
resource-specific manage permission opens the selected record's editor in its
own workspace; it does not add the administrator as a workspace member.
Support retains metadata-only access by default. `/admin/roles` can create
custom roles, but an administrator cannot delegate permissions they do not
hold. Deploy the role-permission migration and restart every web and queue
worker before assigning custom roles.

Account email changes require re-verification. File display names can be changed
without modifying upload provenance or bytes, and localization overrides can be
reset to their English fallback. Suspending an account clears its database
sessions and remember token while leaving already-public content available.

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

## Versioned API

The bearer-token API is rooted at `/api/v1`. Create and revoke credentials in
`/account-settings/api-keys`; every token is permanently bound to the workspace
selected when it was created.

The raw token appears only in the creation response. Store it in a secret
manager; subsequent key listings expose metadata only. Revoke and replace a
lost token.

| Ability                               | Access                           |
| ------------------------------------- | -------------------------------- |
| `links:read`, `links:write`           | Read or manage short links       |
| `bio-pages:read`, `bio-pages:write`   | Read or manage bio pages         |
| `link-pages:read`, `link-pages:write` | Read or manage link pages        |
| `analytics:read`                      | Download workspace analytics CSV |
| `webhooks:read`, `webhooks:write`     | Read or manage webhook endpoints |
| `events:write`                        | Ingest declared custom events    |

Send tokens as `Authorization: Bearer <token>`. Collection endpoints use
`per_page` (default 50, maximum 100) and opaque `cursor` values. On POST create
requests, an optional `Idempotency-Key` replays the original response for an
identical request; using the same key for a different request returns `409`.
Keys are scoped to the token and workspace and expire after 24 hours.

Custom events must be declared and active in the token's workspace. Ingestion
accepts a resource reference, not an arbitrary event payload, and stores no
visitor hash, IP address, user agent, country, or UTM dimensions. Analytics CSV
exports also omit visitor hashes. Webhook secrets are returned only by the
creation response; subsequent reads omit them.

Scramble generates the API reference at `/docs/api` and OpenAPI JSON at
`/docs/api.json` in local and testing environments. The default token rate limit
is 60 requests per minute, configurable with `API_RATE_LIMIT_PER_MINUTE`.

## Notifications and optional email

In-app notifications are stored in the database and work without email.
Category email is sent only when the recipient enables that category and the
Laravel mailer is configured. The local default, `MAIL_MAILER=log`, writes
messages to the application log rather than sending externally. Configure the
standard `MAIL_*` transport variables and verify deliverability before relying
on email notifications.
The development Compose overlay overrides these values only inside its
containers and sends mail to Mailpit; it does not change the host `.env`.

## Docker development runtime

Docker Engine with Docker Compose v2 is required. The default local database
is SQLite at `database/database.sqlite`. Development uses `compose.dev.yaml`
as an overlay so the app, queue worker, Vite server, scheduler, and Mailpit run
together:

```bash
docker compose -f compose.yaml -f compose.dev.yaml up -d app queue vite scheduler mailpit
```

The host `.env` is persistent and operator-owned. Copy the template only when
the file does not exist, then keep its generated `APP_KEY`; no container
startup command creates or replaces that key.

For localhost-only use, keep `APP_URL=http://localhost:8000`,
`VITE_HMR_HOST=localhost`, and `VITE_HMR_PORT=5173`. For LAN access, use the
development machine's browser-reachable address and list localhost as an
accepted alias, for example:

```dotenv
APP_URL=http://192.168.100.19:8000
LINKPILOT_DEFAULT_HOSTS=localhost,127.0.0.1
VITE_HMR_HOST=192.168.100.19
VITE_HMR_PORT=5173
```

`APP_URL` remains the canonical origin used in generated links.
`LINKPILOT_DEFAULT_HOSTS` accepts additional browser hostnames, and
`VITE_HMR_HOST` must be reachable by every browser that opens the app. Vite
still binds inside Docker to `0.0.0.0`; generated asset and HMR URLs use the
browser-reachable host instead.

### First run

```bash
cp .env.example .env
touch database/database.sqlite
docker compose -f compose.yaml -f compose.dev.yaml build app
docker compose -f compose.yaml -f compose.dev.yaml run --rm --no-deps app composer install
docker compose -f compose.yaml -f compose.dev.yaml run --rm --no-deps app npm install
docker compose -f compose.yaml -f compose.dev.yaml run --rm --no-deps app php artisan key:generate
docker compose -f compose.yaml -f compose.dev.yaml run --rm --no-deps app php artisan migrate --force
docker compose -f compose.yaml -f compose.dev.yaml run --rm --no-deps app php artisan db:seed --force
docker compose -f compose.yaml -f compose.dev.yaml run --rm --no-deps app php artisan linkpilot:grant-admin admin@linkpilot.test
docker compose -f compose.yaml -f compose.dev.yaml up -d app queue vite scheduler mailpit
```

Run `cp` and `key:generate` only for a new local environment. Existing
operators should start at the build/install or migration step without
overwriting `.env`. `DemoAccountSeeder` converges `user@linkpilot.test` and
`admin@linkpilot.test`; their shared local-only password is supplied in the
run handoff and is never stored in tracked configuration. Seeding does not
grant administrator access, so the explicit grant command is required.

After pulling schema changes, rerun:

```bash
docker compose -f compose.yaml -f compose.dev.yaml run --rm app php artisan migrate --force
```

Open LinkPilot at [http://localhost:8000](http://localhost:8000), or at the
configured LAN `APP_URL`. Mailpit is available at
[http://localhost:8025](http://localhost:8025) (and the same port on the LAN
host). The development overlay sends SMTP to `mailpit:1025`; production
`MAIL_*` values remain owned by the deployment environment.

Stop only this development stack with:

```bash
docker compose -f compose.yaml -f compose.dev.yaml down
```

## Verification commands

```bash
docker compose run --rm -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app php artisan test
docker compose run --rm app npm run build
```

## Production asset rebuild

After changing React or CSS source, rebuild the Vite manifest:

```bash
docker compose run --rm app npm run build
```
