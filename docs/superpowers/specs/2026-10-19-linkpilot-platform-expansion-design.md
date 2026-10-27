# LinkPilot Platform Expansion Design

## Intent

LinkPilot already provides short links, folders, availability controls, access codes, QR distribution, basic bio pages, and aggregate per-link clicks. The approved expansion brings the remaining operational surfaces observed in the BeLink reference into LinkPilot without copying its branding, implementation, or routes. The first complete expansion adds workspace collaboration, governed publishing domains, public conversion surfaces, analytics, integrations, usage controls, and platform administration.

The product remains a Laravel 13 + Inertia React application. New dashboard surfaces use the repository's existing base shadcn/Radix components and Lucide icons; public pages stay deliberately simple, responsive, and keyboard-accessible.

> **Implementation status update:** The user deferred workspace collaboration, membership, invitations, and active-workspace migration until further instruction. This document remains the detailed future workspace contract. Current implementation must instead follow `docs/superpowers/specs/2026-10-19-linkpilot-user-scoped-expansion-design.md` and use existing user ownership only.

## Reference evidence

REA observed the reference login page at `https://belink.vebto.com/login` and statically analyzed all 123 observed JavaScript artifacts with zero parse failures in evidence `ev_3166f9b5f54416181d58a8a0ccf45470f64f0c7dc8c7f4032009525692347a0d`. Its route inventory contains custom domains, link overlays, tracking pixels, link pages, analytics, plans and billing, workspaces and invites, webhooks, API keys, and administrator routes for users, plans, permissions, security, and settings. Static routes are an inventory rather than proof of server behavior; LinkPilot deliberately implements its own model and wording.

## Goals

1. A user has a personal workspace and can create, switch, invite members to, and administer shared workspaces.
2. Existing and new publishing resources are scoped to an active workspace while retaining the original creator for audit history.
3. Workspace administrators can manage custom domains, tracking pixels, link pages, CTA overlays, webhooks, and billing controls; members can manage content.
4. A verified custom domain can host only the LinkPilot short links assigned to it.
5. Public link pages and CTA overlays render safe provider-specific tracking pixels, record privacy-minimal events, and only redirect after their relevant access checks pass.
6. Workspace and per-link analytics expose time-filtered clicks, unique privacy-preserving visitors, event counts, referrers, browsers, and platforms.
7. Usage limits and plan state are enforced before new metered resources are created.
8. Outbound webhooks have HTTPS-only endpoint validation, HMAC signatures, delivery history, and queued retries.
9. A platform administrator can inspect users and workspace activity, suspend accounts, assign plans, and manage administrator access without a self-escalation path.

## Explicit non-goals

- Copying the reference application's assets, copy, client bundle, API, or brand.
- Collecting raw IP addresses, browser fingerprints, or precise visitor location.
- Inserting arbitrary user-supplied JavaScript into public pages.
- DNS hosting, certificate issuance, reverse-proxy configuration, or wildcard DNS setup. LinkPilot verifies a DNS TXT claim; deployment must still route the verified hostname to this application.
- Live credit-card collection, Stripe/PayPal checkout, invoices, taxes, or provider webhooks. No merchant credentials or product catalog are available. LinkPilot supplies a real local plan/subscription/usage control plane; a payment-provider integration is a separately credentialed migration.
- Embedding a CTA over a third-party destination. Browser same-origin rules make that unsafe and unreliable. LinkPilot implements CTA overlays as a branded interstitial before the destination.
- The wider reference catalog: API-key issuance, notifications, standalone file/events products, QR styling/direct-destination records, link tags/preview fetching, advanced bio widgets/themes/embeds, localization, and global SEO/email/auth/GDPR configuration.

## Product and UI direction

The existing LinkPilot sidebar is the product's visual system. New sections use compact shadcn cards, tables, dialogs, inputs, selects, switches, badges, and visible focus states rather than adding a second component library or decorative dashboard treatment. The UI-guidance lookup suggested a generic glass SaaS aesthetic; that conflicts with the established neutral shadcn application and is intentionally rejected. Dashboard pages prioritize scanability at desktop width and stack controls at mobile width. Public pages use a single focused action, readable text, and no unrequested motion.

## Ownership and authorization

### Workspace roles

| Role     | Content | Active integration association | Integration records | Members                                 | Billing | P1 workspace deletion |
| -------- | ------- | ------------------------------ | ------------------- | --------------------------------------- | ------- | --------------------- |
| `owner`  | manage  | select                         | manage              | manage admins and members               | manage  | unavailable           |
| `admin`  | manage  | select                         | manage              | invite, change, and remove members only | view    | unavailable           |
| `member` | manage  | select                         | view                | view                                    | view    | unavailable           |

Content means folders, short links, QR access, bio pages, link pages, and overlays. A member may select an already-active workspace domain, overlay, or pixel while editing content, but cannot create, edit, delete, activate, or deactivate an integration record. A platform administrator is not silently made a member of every workspace; platform authority is restricted to `/admin` controls.

P1 has one immutable workspace owner. Owners can grant or change `admin` and `member`; admins can invite, edit, and remove only `member`; neither role can invite or assign `owner`. The owner cannot remove or demote themselves, and ownership transfer, guest roles, and workspace deletion are deferred. A personal workspace cannot be deleted in P1.

Invitations are active for seven days. A new invitation for the same pending workspace/email pair revokes and replaces the older one with a new raw token. The token is shown once in the create/resend response and sent through the configured Laravel mailer; only its SHA-256 digest is stored. An owner/admin can list, resend, or revoke pending invitations. Existing members cannot be reinvited. Acceptance requires an authenticated, non-suspended user with the normalized invited email, consumes the invitation atomically, and creates exactly one membership.

Suspending a user deletes their database sessions, clears their remember token, prevents new authenticated access and invitation acceptance, but does not disable public content owned by a workspace that may have other members.

### Active workspace

Each user owns a personal workspace created with their account. `users.current_workspace_id` selects their active membership. Every existing user receives a personal workspace during migration, is attached as its `owner`, and has all existing links, folders, and bio pages backfilled to it. Existing `user_id` columns remain creator/audit ownership and are not removed.

The active workspace is stored on the user record, not in an untrusted browser-only preference. Every controller resolves it from a valid membership; an invalid pointer falls back to the user's owned personal workspace, then their first remaining membership, and persists that repair. A newly created user always has a personal workspace before an authenticated resource is created.

## Data model

### Collaboration and plans

- `workspaces`: `owner_id`, unique `slug`, `name`.
- `workspace_user`: unique workspace/member pair, `role`, timestamps.
- `workspace_invitations`: workspace, inviter, normalized email, role (`admin` or `member`), SHA-256 token digest, expiry, accepted/revoked timestamps.
- `users`: nullable `current_workspace_id`, `is_admin`, `is_suspended`, nullable `suspended_at`.
- `plans`: unique code, name, monthly price in minor units, currency, nullable limits for links, domains, pixels, members, webhooks, and link pages, active flag.
- `workspace_subscriptions`: one current local entitlement per workspace, plan, `active` status, and `manual` source metadata.

The migration seeds this exact USD catalog. A `null` resource limit means unlimited; initial plans otherwise use the values below.

| Code       | Monthly minor units |     Links |   Domains |    Pixels |   Members |  Webhooks | Link pages |
| ---------- | ------------------: | --------: | --------: | --------: | --------: | --------: | ---------: |
| `free`     |                   0 |        25 |         0 |         0 |         1 |         0 |          1 |
| `pro`      |                2900 |       500 |         3 |        10 |         5 |         5 |         25 |
| `business` |                9900 | unlimited | unlimited | unlimited | unlimited | unlimited |  unlimited |

Personal and newly created shared workspaces begin on `free`; P1 subscriptions are always `active`. A platform administrator can directly replace a workspace plan in one transaction. A user can only choose the free plan for their own workspace. A downgrade never deletes, disables, or unpublishes existing resources: it blocks only future creation of the over-limit resource and visibly reports the count. Links, domains, pixels, webhooks, and link pages count whether active or inactive; active members include the owner; unexpired pending invitations reserve a member slot. Deletion, invitation revocation, or expiry frees capacity. Acceptance rechecks the member limit under a workspace lock.

### Workspace-owned publishing resources

`links`, `folders`, and `bio_pages` gain a nullable `workspace_id` foreign key and are backfilled. New resources always write a workspace ID. A transition-safe nullable column preserves existing rows during expand-and-backfill deployments; application readers require an active workspace after migration.

- `custom_domains`: workspace, normalized hostname, verification token, status (`pending_dns`, `pending_tls`, `active`, `failed`), verification timestamp, last-check timestamp, and bounded failure reason.
- `tracking_pixels`: workspace, name, provider (`meta_pixel` or `google_analytics`), provider ID, active flag.
- `link_pages`: workspace, name, public slug unique within the link-page surface, title, description, publish flag.
- `link_page_items`: link page, title, destination URL, active flag, position.
- `link_overlays`: workspace, name, headline, body, CTA label, active flag.
- `links.custom_domain_id` and `links.link_overlay_id` are nullable `nullOnDelete` foreign keys.
- `link_page_tracking_pixel` and `link_overlay_tracking_pixel` attach a reusable workspace pixel to public surfaces. Each page or overlay can attach zero to five active workspace pixels.

Short-link slugs, bio handles, and link-page slugs are unique within their own route namespace, not across tables. A custom-domain link resolves only when the request host matches its `active` domain; a default-host request resolves only a link without a custom domain. Link pages and bio pages remain on configured default hosts in P1.

### Analytics and integrations

- `analytics_events`: immutable UUID event ID, workspace, nullable link/page/page-item/bio-page/bio-link/overlay reference, event type, occurred timestamp, HMAC visitor hash, visitor key version, referrer host, coarse browser, and coarse platform.
- `webhooks`: workspace, name, HTTPS URL, encrypted signing secret, selected event names, revision, active flag, and secret-rotation timestamp.
- `webhook_deliveries`: nullable webhook reference, webhook revision, event UUID/name, exact JSON body, attempt count, state (`pending`, `delivered`, `failed`, `cancelled`), response code, bounded response excerpt, failure detail, and completion timestamp.

`clicks` remains the compatible all-time short-link click counter and is never backfilled into `analytics_events`. Link insight totals and daily short-link click series retain `clicks`, including historical data. Rich dimensions and tracked-event totals use consented `analytics_events` only. All 7/30/90-day windows are UTC calendar-day windows from inclusive midnight through the next exclusive midnight.

Each public interaction references its exact content object: `link_page.clicked` references its item, `bio_page.clicked` references its bio link, and `overlay.view` references its overlay. The public delivery path is more important than telemetry: click creation continues before redirect, while analytics or webhook enqueue failures are caught, logged, and do not block a public render or redirect.

## Public delivery behavior

### Custom domains

An administrator enters a hostname. LinkPilot normalizes it, rejects IP literals, localhost, configured default hosts, and malformed values, then displays the exact TXT record:

```text
_host: _linkpilot-verify.<domain>
value: linkpilot=<token>
```

Verification first requires an exact TXT match, then requires the domain to serve LinkPilot's token response at `https://<domain>/.well-known/linkpilot-domain/<token>` with a valid TLS certificate, no redirect, and a public resolved address. The first failure state is `pending_dns`; a successful TXT check with an unreachable/TLS-invalid delivery probe is `pending_tls`; full proof is `active`; failed revalidation is `failed`. A scheduled daily revalidation repeats both checks. An inactive domain cannot be newly assigned or used as a canonical URL; already assigned links remain bound and unavailable rather than silently falling back to the default host. Reissuing a token returns the domain to `pending_dns`. Deleting a domain explicitly warns that it clears assigned links and restores their default-host delivery.

Public host resolution accepts only the parsed `APP_URL` host plus explicitly configured `LINKPILOT_DEFAULT_HOSTS`; unknown hosts return 404. It uses Laravel's normalized request host, ignores unconfigured forwarded host headers, and permits no custom-domain port. Default canonical URLs come from `APP_URL`; active custom-domain URLs always use `https://<domain>`.

### Link pages and pixels

A link page publishes at `/pages/{slug}` and has ordered active buttons. The public page records `link_page.view`; its button route records `link_page.clicked` then redirects. A page may attach active workspace pixels.

Public rendered surfaces present an explicit tracking choice before any enriched event or third-party pixel runs. The server stores `granted` or `denied` in a same-site consent cookie for 180 days. Direct 302 links retain the existing aggregate `clicks` counter but do not create a rich event because no consent surface is shown. Once consent is granted, React can create only the fixed Meta Pixel or Google Analytics tag shape with validated IDs; Google Tag Manager and arbitrary snippets are excluded. Pixel providers receive a page view once per public render after consent.

### CTA overlays

A short link may choose one active workspace overlay. Visiting that link passes the existing availability and access-code checks, records `overlay.view` after consent, and renders a branded interstitial. Continuing repeats the access checks, records one click and consented `link.clicked`, then issues the outbound redirect. A link without an overlay keeps its direct 302 behavior. Overlay pixels use the same fixed-provider, consent-gated renderer.

### Access-code hardening

Unlocks are link-specific, last eight hours, and store the current `access_code_version` in the server session. Replacing or clearing a code increments that version and invalidates every prior unlock. Failed submissions are limited to five attempts per five minutes per link and visitor cache key; Laravel's hash comparison remains the only code comparison.

## Analytics and privacy

`AnalyticsTracker` records an enriched event only after explicit consent. It extracts only the referring host and coarse user-agent categories, derives `visitor_hash` with HMAC-SHA-256 of IP and user agent using `ANALYTICS_HASH_KEY`, and immediately discards the raw values. Production requires a nonempty analytics key, `ANALYTICS_HASH_KEY_VERSION`, and `PUBLIC_PRIVACY_URL`; until those are configured, rich analytics and third-party pixels stay disabled. Rotation affects only new events, and unique-visitor counts never merge hash versions. Events are retained for 90 days and a daily prune job deletes expired rows. The dashboard supports 7, 30, and 90-day windows and returns zero-filled daily short-link counts, total short-link clicks, consented unique visitor count, event type totals, and top referrer/browser/platform lists. Workspace analytics aggregates only the active workspace; link insights aggregate only the selected authorized link.

## Webhooks

Supported webhook names are `link.clicked`, `overlay.view`, `link_page.view`, `link_page.clicked`, `bio_page.view`, and `bio_page.clicked`. Dispatch creates an auditable delivery row and queues a job only for a persisted, consented analytics event. Webhook creation generates a random 32-byte secret, encrypts it at rest, and reveals it only once; an administrator can rotate it, which cancels pending deliveries for that webhook.

The exact body preserves this key order and uses JSON with unescaped slashes/unicode:

```json
{
    "schema_version": 1,
    "event_id": "uuid",
    "event": "link.clicked",
    "occurred_at": "2026-10-19T12:00:00Z",
    "workspace_id": 123,
    "data": {
        "link": { "id": 456, "slug": "launch" }
    }
}
```

`data` adds only the relevant item, overlay, page, or bio-page identifiers; it never includes raw visitor data, destination URLs, access codes, hashes, or encrypted secrets. The same event UUID and exact serialized body are reused for every retry.

```text
Content-Type: application/json
User-Agent: LinkPilot-Webhooks/1.0
X-LinkPilot-Event: <event>
X-LinkPilot-Signature: sha256=<HMAC-SHA-256 exact-body signature>
```

Creation and every attempt resolve the endpoint fresh, reject unresolved/private/loopback/link-local/reserved results, and force the HTTP client to a validated public address while retaining TLS hostname validation. Redirects are disabled. A 2xx response is success; every other response/transport failure is retryable. A delivery has three total attempts: initial, then 60-second and 300-second retries, with 3-second connect and 10-second total timeouts. Updating, disabling, rotating, or deleting a webhook cancels its pending deliveries; delivery history remains. Compose includes a worker service so local delivery retries have a consumer.

## Administration

`/admin` requires `users.is_admin`. Its dashboard lists user/workspace metadata, subscription state, and aggregate counts only; it does not grant non-member access to content destinations or visitor dimensions. A platform administrator can suspend/unsuspend users, grant/revoke other administrators, and assign workspace plans. They cannot remove their own administrator flag or suspend themselves. Suspension revokes existing database sessions and blocks new authenticated access; public workspace content remains available. `php artisan linkpilot:grant-admin {email}` is the explicit local/operations provisioning path; no registration or dashboard form can self-grant platform authority.

## Migration path and rollback boundary

1. **Expand:** create all new tables and nullable foreign keys. No existing reader relies on the new columns yet.
2. **Migrate:** create personal workspaces, memberships, current-workspace pointers, free subscriptions, and backfill each existing workspace-owned resource.
3. **Verify:** run migrations against the existing SQLite database, inspect counts, exercise legacy links/bio pages, and run the complete suite.
4. **Contract:** intentionally not part of this release. Existing `user_id` ownership remains and workspace foreign keys remain nullable to protect mixed-version rollback. Rolling back this release drops only the newly added structures/columns after an explicit backup; production rollback is an operator decision, not an automatic deploy action.

## Acceptance criteria

- Existing data receives a personal workspace without losing public links, folders, bio pages, or creator attribution.
- A collaborator sees and manages only the currently active workspace's content; a non-member receives 403.
- Invitation lifecycle enforces the selected non-owner role, email match, expiry, revocation, and one accepted membership.
- A suspended user cannot access the authenticated product or accept invitations; a platform admin cannot self-demote or self-suspend.
- Custom-domain activation requires exact DNS proof, LinkPilot HTTPS challenge/TLS proof, and a matching active public host; a failed recheck pauses rather than reroutes delivery.
- Link pages and overlays honor link controls, prompt for consent before enriched events/pixels, and inject only fixed validated Meta/GA providers.
- Analytics dimensions contain no raw IP data, only record after consent, use versioned HMAC hashes, retain data for 90 days, and are scoped to the active workspace/link and selected UTC window.
- Metered creation fails before exceeding the active plan's limit while downgrade preserves existing data.
- Webhooks reject unsafe URLs on every attempt, sign the exact versioned JSON body, retain delivery state, cancel stale revisions, and retry through the queue worker.
- Feature tests, the complete Laravel suite, production Vite build, migration on the persistent database, and manual browser smoke paths pass.

## Subsequent-slice catalog

The following reference inventory remains intentionally catalogued, not silently omitted. It becomes eligible only after the platform expansion above is stable:

| Slice               | Deferred capability                                                                   | Trigger to start                                                      |
| ------------------- | ------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| Developer platform  | API keys and authenticated public API                                                 | A consumer needs automation beyond outbound webhooks.                 |
| Bio depth           | widgets, media embeds, social profiles, themes, scheduling, and bio-specific insights | A creator needs a richer public profile than link pages.              |
| Link creation depth | tags, previews, UTM builder, direct QR records, QR styling, files, and exports        | A team needs campaign production at scale.                            |
| Billing provider    | Stripe/PayPal checkout, invoice/tax handling, provider webhooks                       | Merchant account, products, keys, and compliance policy are supplied. |
| Platform settings   | notifications, localization, email/auth/GDPR/SEO/system configuration                 | There is an operational owner for product-wide policy.                |
| Analytics depth     | geographic aggregation, live traffic, custom events, attribution, and exports         | Privacy policy and data-processing basis are approved.                |
