# LinkPilot User-Scoped Platform Expansion Design

## Status and authority

The user explicitly deferred workspace collaboration, memberships, invitations, active-workspace selection, and workspace data migration until further instruction. This is the implementation authority for the current slice. `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md` remains the detailed future collaboration contract; it is not executable now.

LinkPilot therefore delivers the remaining SaaS modules against the existing authenticated `users.id` ownership boundary. Every new private record belongs to exactly one user. No current migration may add `workspaces`, `workspace_user`, invitation, role, or `workspace_id` structures. A future workspace migration must preserve `user_id` attribution and migrate the user-scoped relations described here.

## Reference and goals

REA statically analyzed all 123 observed reference JavaScript artifacts with zero parse failures in evidence `ev_3166f9b5f54416181d58a8a0ccf45470f64f0c7dc8c7f4032009525692347a0d`. It catalogued domains, overlays, pixels, link pages, analytics, billing, workspaces, webhooks, API keys, and administration. That inventory is not a server-behavior claim and does not authorize copying its branding, client bundle, routes, assets, or APIs.

This slice delivers these user-owned capabilities:

1. Local plan/subscription and usage enforcement without a pretend payment provider.
2. Platform administrator controls and safe account provisioning/suspension.
3. Verified custom-domain delivery for a user's assigned links.
4. Consent-gated richer analytics and public bio interaction tracking.
5. Fixed-provider Meta Pixel / Google Analytics integrations and public link pages.
6. CTA overlays as LinkPilot-controlled interstitials.
7. Signed queued webhooks with auditable delivery/retry history.

The existing neutral shadcn/Radix and Inertia visual system remains authoritative. New UI uses existing base cards, tables, dialogs, inputs, selects, switches, badges, and Lucide icons. No second UI library, copied reference design, or decorative redesign is permitted.

## Ownership and authorization

Existing links, folders, and bio pages remain owned by `user_id`. Every active module below is user-owned:

- A user can list, create, update, attach, and delete only records where `user_id` matches the authenticated user.
- A link may select only an active custom domain or active overlay owned by the same user.
- A link page or overlay may attach zero to five active pixels owned by the same user.
- Account analytics aggregates the authenticated user’s content only. Per-link insight uses current link policy authorization.
- A platform administrator is not implicitly authorized to read another user’s content destinations, rich analytics dimensions, domains, pixels, pages, overlays, or webhook secrets. `/admin` returns metadata and aggregate counts only.

The existing owner-only resource policies stay the access boundary. New resource policies use the same user-ID ownership rule. The future workspace design replaces this with member roles; it is deliberately not approximated now.

## Account plans and local entitlement

Create `plans` and one `user_subscriptions` record per user. Subscription state is a genuine internal entitlement but never payment collection. The exact initial catalog is:

| Code       | Monthly minor units |     Links |   Domains |    Pixels |  Webhooks | Link pages |
| ---------- | ------------------: | --------: | --------: | --------: | --------: | ---------: |
| `free`     |                   0 |        25 |         0 |         0 |         0 |          1 |
| `pro`      |                2900 |       500 |         3 |        10 |         5 |         25 |
| `business` |                9900 | unlimited | unlimited | unlimited | unlimited |  unlimited |

`null` is unlimited. Every existing and newly registered user receives a single `active`/`manual` free subscription. A user may self-select only free. A platform administrator may assign any active plan to another user in one transaction. All existing links, pending/active domains, pixels, webhooks, and link pages count toward their owner’s usage. A downgrade preserves all records and public behavior; only later creation of an over-limit metric fails. Creation and plan assignment lock the user row before checking use.

No Stripe/PayPal checkout, invoice, taxes, cancellation, provider webhook, or payment form is added. Those require merchant credentials, products/prices, billing policy, and compliance approval.

## Account and administration

`users` gains `is_admin`, `is_suspended`, and nullable `suspended_at`. `active-account` middleware runs after `auth`/`verified`, blocks suspended users from authenticated routes, and invitation handling does not exist in this slice. `administrator` requires `is_admin`.

`/admin` lists account metadata, subscription code/status, and aggregate counts. It does not expose private content, raw visitor data, destination URLs, or secret material. A platform admin can suspend/unsuspend another user, grant/revoke another administrator flag, and assign a plan to another account. They cannot suspend or demote themselves. Suspension is transactional: set state/time, clear the remember token, and delete database sessions. It does not break public content because that content is account-owned and may remain published.

`php artisan linkpilot:grant-admin {email}` is the only local operations provisioning route. Registration and dashboard forms never self-grant administrator access.

## Custom domains

`custom_domains` belongs to a user and has normalized hostname, verification token, status (`pending_dns`, `pending_tls`, `active`, `failed`), verification/last-check timestamps, and a bounded failure reason. Input rejects IP literals, localhost, configured default hosts, malformed hosts, and duplicate claims. The dashboard exposes exactly:

```text
_host: _linkpilot-verify.<domain>
value: linkpilot=<token>
```

Verification requires both an exact TXT match and a public no-redirect HTTPS response at `https://<domain>/.well-known/linkpilot-domain/<token>` carrying the challenge token under valid TLS. TXT failure yields `pending_dns`; after TXT succeeds, delivery/TLS failure yields `pending_tls`; both checks yield `active`; failed later revalidation yields `failed`. A daily command rechecks every retained domain. Token reissue returns the domain to `pending_dns`.

Only an active user-owned domain can be assigned to a link. The request host must be the active assigned hostname to resolve such a link. A default host resolves only unassigned links; unknown hosts 404. Failed assignments stay bound and unavailable rather than falling back to the default host. Deleting a domain requires an explicit confirmation and clears its assignments; that is the only path back to default-host delivery. Canonical custom URLs use `https://<domain>`; default URLs use `APP_URL`. Parsed `APP_URL` plus explicit `LINKPILOT_DEFAULT_HOSTS` are the only default hosts; forwarded host headers are not trusted and custom-domain ports are rejected.

DNS hosting, certificate issuance, reverse-proxy configuration, wildcard DNS, and provider APIs are external infrastructure, not code in this slice.

## Consent, analytics, and access codes

`analytics_events` is immutable and stores a UUID, owner `user_id`, nullable exact content references, event name, occurrence time, HMAC visitor hash, visitor-key version, referring host, coarse browser, and coarse platform. It never stores raw IP, full user agent, destination URL, access code, hash, token, or secret.

A public LinkPilot-rendered surface offers a server-written `granted`/`denied` consent cookie for 180 days before any rich event or third-party pixel. In production, rich tracking is disabled unless `PUBLIC_PRIVACY_URL`, `ANALYTICS_HASH_KEY`, and `ANALYTICS_HASH_KEY_VERSION` are configured. `AnalyticsTracker` derives an HMAC-SHA-256 hash from IP and user agent, then discards raw values. A daily command removes events older than 90 days. Windows are UTC 7/30/90-day periods, inclusive from midnight through the next exclusive midnight.

The existing `clicks` table remains the all-time direct-link metric and is never backfilled. A direct short-link 302 still writes the compatible click after availability/access checks but emits no rich event because it offers no consent surface. Link/page/bio/overlay telemetry failures are caught and logged; public rendering and redirection continue.

Access-code unlocks become link-specific, valid for eight hours, and versioned in the server session. Replacing or clearing a code increments its version and invalidates every prior unlock. Failed submissions are limited to five per five minutes per link and visitor cache key. Laravel’s hash comparison remains the only code comparison.

## Pixels, link pages, and overlays

`tracking_pixels` belongs to a user with name, provider (`meta_pixel` or `google_analytics`), validated provider ID, and active state. Only fixed provider renderers are allowed. Google Tag Manager, arbitrary script, remote page injection, and custom snippets are rejected.

`link_pages` belongs to a user with name, public slug unique only within `/pages`, title, description, and published state. Ordered active `link_page_items` hold validated HTTP(S) destinations. A published page renders at `/pages/{slug}`. It writes `link_page.view` after consent; its button route writes `link_page.clicked` with the exact item then redirects. Unknown/unpublished pages 404. A page can attach zero to five active owned pixels.

`link_overlays` belongs to a user with name, headline, body, CTA label, and active state. A link can select one active owned overlay. Visiting an eligible overlay link passes existing availability/access checks then renders a LinkPilot-controlled interstitial; it does not count a click. After consent it may write `overlay.view`. `POST /{slug}/continue` repeats access/availability checks, writes one compatible click plus a consented `link.clicked`, then redirects. A link without an overlay retains direct 302 behavior. An overlay can attach zero to five active owned pixels. Embedding an overlay in the third-party destination is not supported.

## Webhooks

`webhooks` belongs to a user and stores name, HTTPS URL, encrypted signing secret, selected event names, revision, active flag, and secret-rotation time. `webhook_deliveries` retains a nullable webhook reference, revision, event UUID/name, exact serialized body, attempt count, state (`pending`, `delivered`, `failed`, `cancelled`), response code, bounded response excerpt/failure detail, and completion time.

Supported event names: `link.clicked`, `overlay.view`, `link_page.view`, `link_page.clicked`, `bio_page.view`, and `bio_page.clicked`. Persisting a consented analytics event creates delivery rows and dispatches an encrypted queued job. Creation and every attempt freshly resolve the endpoint; unresolved/private/loopback/link-local/reserved answers fail. Verified implementation detail: literal addresses (including bracketed IPv6) are rejected outright, and the blocked set additionally covers carrier-grade NAT, benchmarking, documentation, and address-embedding tunnelling ranges that PHP's reserved-range filter accepts. The HTTP client connects only to the validated public address — a `CURLOPT_RESOLVE` entry, with IPv6 addresses bracketed — while retaining TLS hostname validation, uses 3-second connect and 10-second total timeouts, and never follows redirects. Any 2xx succeeds; all others retry.

The exact JSON uses unescaped Unicode/slashes and this key order:

```json
{
    "schema_version": 1,
    "event_id": "uuid",
    "event": "link.clicked",
    "occurred_at": "2026-10-19T12:00:00Z",
    "account_id": 123,
    "data": {
        "link": { "id": 456, "slug": "launch" }
    }
}
```

`data` holds only relevant IDs/slug fields. It never includes raw visitor data, destination URLs, access codes, hashes, or secrets. The exact body/event UUID is reused on every retry and signed with `X-LinkPilot-Signature: sha256=<HMAC-SHA-256 exact-body>`, with `Content-Type: application/json`, `User-Agent: LinkPilot-Webhooks/1.0`, and `X-LinkPilot-Event`. There are three total attempts: immediate, +60 seconds, and +300 seconds. Updating, disabling, rotating, or deleting a webhook cancels pending deliveries but preserves history. Secrets are random 32-byte values encrypted at rest and shown only on create/rotation. Compose runs a queue worker.

## Migration and verification boundary

1. Expand only user-owned tables/columns and nullable resource foreign keys where independently needed.
2. Backfill every existing user with a free subscription, preserving all existing data unchanged.
3. Verify persistent SQLite migration, public legacy links/bio pages, every focused feature suite, all backend tests, and a production Vite build.
4. Use browser smoke proof against the local running service. Custom-domain verification may be demonstrated as pending locally; live activation needs real public DNS/TLS/routing. A live webhook test must use a safe public endpoint and never relax SSRF protections for localhost.

A later workspace migration adds workspace ownership and collaboration without deleting user ownership. It must migrate subscriptions and user-owned integration records only after the user explicitly reopens that slice.

## Acceptance criteria

- Existing user-owned links/folders/bio pages remain visible and public behavior is unchanged.
- Every user has an active free local subscription; limits reject only new over-limit creation and downgrades preserve retained data.
- Suspended users lose authenticated access; platform admins cannot self-suspend/demote; public content remains available.
- A custom domain activates only after exact TXT and HTTPS/TLS proof; bad/unknown/default host routing never exposes an assigned link.
- Enriched analytics/pixels require consent, contain no raw visitor data, retain 90 days, and cannot block public delivery.
- Link pages and overlays render only fixed Meta/GA integrations, protect publication/continue paths, and reference the exact interacted item/content.
- Webhooks prevent SSRF on every attempt, sign exact immutable bodies, preserve history, cancel stale revisions, and retry via the running worker.
- Workspaces, memberships, invitations, active-workspace scoping, and collaboration roles are absent from migrations, routes, UI, and runtime behavior until the user asks for them.
