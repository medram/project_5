# LinkPilot Product Requirements Document

> Durable feature catalog and delivery contract for reproducing the useful SaaS capabilities observed in the BeLink reference without copying its branding, source, routes, assets, or proprietary implementation.

## 1. Product definition

LinkPilot is a Laravel + React publishing console for teams that create, govern, distribute, measure, and automate short links and public link destinations.

**Primary user:** a marketer, creator, or operations teammate who needs a branded destination, controlled delivery, understandable performance data, and reliable collaboration without handling raw analytics infrastructure.

**Core promise:** create a link once, decide where and when it can be used, distribute it through a short URL, QR code, bio page, or link page, then see attributable privacy-minimal outcomes in the workspace that owns it.

## 2. Research basis and confidence

REA static analysis of `https://belink.vebto.com/login` is recorded as evidence `ev_3166f9b5f54416181d58a8a0ccf45470f64f0c7dc8c7f4032009525692347a0d`. It parsed **123/123** scripts with zero parse failures and found routes/modules for short links, QR codes, link-in-bio, analytics, folders, custom domains, CTA overlays, tracking pixels, link pages, billing, workspaces, invitations, webhooks, API keys, and platform administration.

This document treats that result as an **observed feature inventory**, not proof of reference server behavior. LinkPilot requirements below are independent product decisions.

## 3. Delivery status

| Status | Meaning |
|---|---|
| Delivered | Present in LinkPilot and covered by current verification. |
| In active platform expansion | Designed and scheduled in the current implementation plan; do not describe as shipped until verified. |
| Catalogued | A future requirement, intentionally retained here with scope and trigger. |
| External prerequisite | Requires credentials, policy, or infrastructure that the repository does not contain. |

### 3.1 Delivered foundation

- Owned short-link CRUD with validated HTTP(S) destinations and custom slugs.
- Private folders and per-link folder assignment.
- Link activation, UTC availability windows, access-code protection, and public 404 behavior before availability.
- Locally generated SVG QR codes for owned short links.
- Basic published link-in-bio pages with ordered active outbound buttons.
- Per-link 14-day click count series and total clicks.

### 3.2 Active platform expansion

- Workspaces, memberships, invitations, and active-workspace scoping.
- Custom domains with DNS TXT verification and host-aware public routing.
- CTA-overlay interstitials, safe tracking pixels, public link pages, and richer event analytics.
- Plans, local subscriptions, usage limits, webhooks, and platform administration.

The detailed implementation design is `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md`.

## 4. Product principles

1. **Ownership is explicit.** Every content and integration object belongs to one workspace. Creator attribution remains available for audit.
2. **A link is safe by default.** Unsafe outbound schemes, self-redirect loops, inactive custom hosts, and unsafe webhook endpoints are rejected.
3. **Public access checks precede side effects.** Inactive, future, expired, or locked links do not redirect or count a click until access succeeds.
4. **Analytics minimizes visitor data.** No raw IP addresses, precise locations, browser fingerprints, access codes, or secrets are stored in analytics events.
5. **Enriched tracking is consent-gated.** Before a visitor grants tracking consent on a LinkPilot-rendered public surface, LinkPilot emits no analytics event or third-party pixel; direct redirects retain only their aggregate click counter.
6. **No arbitrary customer JavaScript.** Tracking pixels are fixed Meta Pixel or Google Analytics renderers driven by validated identifiers. Google Tag Manager and custom snippets are excluded.
7. **No fake payments.** Payment capture requires merchant credentials and compliance policy. Until then, plans and usage are real internal entitlement state, not a pretend checkout.
8. **Keep the UI native.** New React surfaces use the installed base shadcn/Radix primitives and existing Inertia patterns. Use Lucide icons, visible labels, keyboard focus, and responsive layouts.
9. **Reference parity is functional, not visual cloning.** LinkPilot uses its own names, copy, routes, and design.

## 5. Personas and jobs

| Persona | Main job | Success signal |
|---|---|---|
| Creator | Publish a controlled destination quickly | A link/QR/bio/link page reaches the right visitor at the right time. |
| Marketer | Run and measure a campaign | Links are branded, scheduled, tracked, and explain performance by source/device. |
| Workspace administrator | Govern a team and integrations | Members, domains, pixels, usage, and webhooks stay inside one workspace. |
| Platform administrator | Operate the SaaS safely | Users and workspaces can be inspected, suspended, and entitled without self-escalation. |
| Developer/automation consumer | Receive trusted operational signals | Signed webhooks have stable event names, payloads, and delivery history. |

## 6. Information architecture

### 6.1 Authenticated workspace navigation

| Section | Route family | Purpose |
|---|---|---|
| Short links | `/links` | Create, organize, control, and inspect short links. |
| QR codes | `/dashboard/qr-codes` | Display/download owned link QR codes. |
| Folders | `/dashboard/folders` | Organize workspace links. |
| Bio pages | `/bio-pages` | Publish creator-style multi-link profiles. |
| Link pages | `/link-pages` | Build branded campaign/landing link collections. |
| Analytics | `/analytics` | Aggregate active-workspace traffic and events. |
| Custom domains | `/custom-domains` | Verify and manage branded hosts. |
| CTA overlays | `/overlays` | Configure branded pre-destination interstitials. |
| Tracking pixels | `/tracking-pixels` | Create provider-specific tracking configurations. |
| Workspaces | `/workspaces` | Switch, create, invite, and manage collaboration. |
| Billing and usage | `/billing` | View plans, limits, local subscription state, and usage. |
| Webhooks | `/webhooks` | Manage signed outbound subscriptions and delivery logs. |
| Administration | `/admin` | Platform-only users, status, and plan controls. |

### 6.2 Public destinations

| Surface | Route family | Behavior |
|---|---|---|
| Short link | `/{slug}` | Validate access/availability, optionally show CTA overlay, then redirect. |
| Unlock a short link | `/{slug}/unlock` | Validate the correct code into the server session. |
| Continue an overlay | `/{slug}/continue` | Re-check access, record completion, and redirect. |
| Bio page | `/bio/{handle}` | Render published active bio content. |
| Bio outbound button | `/bio/{handle}/links/{bioLink}` | Record click event then redirect. |
| Link page | `/pages/{slug}` | Render published link-page items and eligible pixels. |
| Link-page button | `/pages/{slug}/items/{item}` | Record click event then redirect. |

A verified custom domain resolves only a link explicitly assigned to that host. The default application host resolves only default-host links.

## 7. Functional requirements

### 7.1 Identity, accounts, and security — Delivered / catalogued depth

#### Current foundation

- Registration, login/logout, password reset, email verification, passkeys, and two-factor authentication use the Laravel starter-kit foundation.
- Authenticated product routes require a verified email.

#### Future account requirements

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| ID-1 | Profile name/email updates preserve verification semantics. | P0 | Delivered foundation |
| ID-2 | Password, passkey, and two-factor management use current Fortify security flows. | P0 | Delivered foundation |
| ID-3 | A suspended account cannot access authenticated LinkPilot resources. | P1 | In active platform expansion |
| ID-4 | Account notifications and notification preferences are configurable. | P3 | Catalogued |
| ID-5 | Account deletion follows retention and billing policy. | P3 | Catalogued |

### 7.2 Short links — Delivered foundation; campaign depth catalogued

#### Required behaviors

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| LNK-1 | A workspace member can create a HTTP(S) short link with destination URL, title, active flag, and a unique normalized slug. | P0 | Delivered; workspace scoping in active expansion |
| LNK-2 | Generated slugs retry on collision; custom slugs reject reserved application paths. | P0 | Delivered |
| LNK-3 | A link can be placed in one folder. | P0 | Delivered; workspace scoping in active expansion |
| LNK-4 | A link can be scheduled with optional UTC start and expiry timestamps. | P0 | Delivered |
| LNK-5 | A link can require an access code stored only as a Laravel hash; P1 makes unlocks link-specific, versioned, eight hours long, and rate-limited to five failed attempts per five minutes per link/visitor cache key. | P1 | Hardening in active platform expansion |
| LNK-6 | Unavailable links return 404 without creating a click or event record. | P0 | Delivered |
| LNK-7 | A link can select one active workspace custom domain. | P1 | In active platform expansion |
| LNK-8 | A link can select one active workspace CTA overlay. | P1 | In active platform expansion |
| LNK-9 | A final short-link delivery writes a rich event only after explicit tracking consent; the compatible aggregate click counter remains independent. | P1 | In active platform expansion |
| LNK-10 | Link list supports filters, search, tags, export, preview, UTM builder, and link-type variants. | P2 | Catalogued |
| LNK-11 | Link edits retain audit history and optional approval workflow. | P3 | Catalogued |

#### Acceptance criteria

- Only active-workspace members can see or modify a workspace link.
- A non-member receives 403 for link management and insights.
- Direct links preserve one 302 redirect after all controls pass.
- Overlay links record final completion only after the visitor chooses to continue.
- Changing or clearing an access code invalidates every prior unlock; an unlocked session never applies to another link.

### 7.3 Folders — Delivered foundation

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| FOL-1 | Create, rename, list, and delete private folders. | P0 | Delivered; workspace scoping in active expansion |
| FOL-2 | Deleting a folder preserves its links and clears `folder_id`. | P0 | Delivered |
| FOL-3 | Folder lists show link count and click/event rollups. | P2 | Catalogued |

### 7.4 QR codes — Delivered foundation; expansion catalogued

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| QR-1 | An owner can retrieve a locally generated SVG QR code for each owned short link. | P0 | Delivered; workspace permission in active expansion |
| QR-2 | QR data uses the link's canonical public URL and obeys link controls. | P0 | Delivered; custom-domain URL in active expansion |
| QR-3 | A workspace can create direct-destination QR records with title, styling, and scan analytics. | P2 | Catalogued |
| QR-4 | QR lists support export and QR-specific insights. | P2 | Catalogued |

### 7.5 Link in bio — Delivered foundation; rich editor catalogued

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| BIO-1 | Users create published/unpublished bio pages with normalized handles. | P0 | Delivered; workspace scoping in active expansion |
| BIO-2 | Users create, reorder, activate, and delete outbound buttons. | P0 | Delivered |
| BIO-3 | Public pages render only published pages and active items in order. | P0 | Delivered |
| BIO-4 | Page views and button clicks record privacy-minimal events. | P1 | In active platform expansion |
| BIO-5 | Content blocks include image, text, social profiles, and media embeds (YouTube, SoundCloud, Vimeo, Spotify, Twitch, TikTok). | P2 | Catalogued |
| BIO-6 | Appearance controls include theme, typography, color, background, and responsive preview. | P2 | Catalogued |
| BIO-7 | Per-block scheduling, pinning, disable rules, and bio-specific insights are available. | P2 | Catalogued |

### 7.6 Link pages — In active platform expansion

**Purpose:** A workspace-owned branded public page for a campaign or curated destination list, distinct from creator-centric bio pages.

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| PAG-1 | A workspace member can create/update/delete a link page with name, a slug unique in the link-page route namespace, title, description, and publish state. | P1 | In active platform expansion |
| PAG-2 | A page has ordered active outbound items with validated HTTP(S) destinations. | P1 | In active platform expansion |
| PAG-3 | `/pages/{slug}` returns 404 when unpublished or unknown. | P1 | In active platform expansion |
| PAG-4 | Page view and item click events are recorded only after explicit tracking consent and never store raw IP data. | P1 | In active platform expansion |
| PAG-5 | A page attaches zero to five reusable, active workspace pixels. | P1 | In active platform expansion |
| PAG-6 | Template selection is eligible when a product owner defines supported templates, preview semantics, and migration behavior. | P3 | Catalogued |
| PAG-7 | Custom CSS is excluded until there is an isolation/CSP security design. | P3 | Catalogued / security prerequisite |
| PAG-8 | Forms and lead capture are eligible after field schema, consent, retention, and access rules are approved. | P3 | Catalogued / privacy prerequisite |
| PAG-9 | File delivery is eligible after storage, file-type/size, malware scanning, and retention policy are approved. | P3 | Catalogued / operational prerequisite |
| PAG-10 | A/B experiments are eligible after assignment, reporting, and consent rules are approved. | P3 | Catalogued / analytics prerequisite |

### 7.7 CTA overlays — In active platform expansion

**Product decision:** Implement as a branded LinkPilot-controlled interstitial. Do not inject content over a remote destination.

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| OVR-1 | A workspace owner/admin can create/edit/delete an overlay with name, heading, body, CTA label, and active state. | P1 | In active platform expansion |
| OVR-2 | A link selects at most one overlay in its own workspace. | P1 | In active platform expansion |
| OVR-3 | An eligible link visit renders the overlay before final delivery. | P1 | In active platform expansion |
| OVR-4 | Continue revalidates link availability/access, records completion, and redirects. | P1 | In active platform expansion |
| OVR-5 | Attached safe provider pixels run on the interstitial only after visitor consent. | P1 | In active platform expansion |
| OVR-6 | Timed interstitial variants, visual experiments, and targeting are eligible only on LinkPilot-controlled overlay pages. Remote-page injection is not a planned capability. | P3 | Catalogued |

### 7.8 Custom domains — In active platform expansion

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| DOM-1 | A workspace owner/admin can enter a normalized hostname for a workspace. | P1 | In active platform expansion |
| DOM-2 | IP literals, localhost, configured default hosts, malformed hosts, and duplicate claims are rejected. | P1 | In active platform expansion |
| DOM-3 | LinkPilot generates a unique token and requires exact DNS TXT proof at `_linkpilot-verify.<domain>`. | P1 | In active platform expansion |
| DOM-4 | A domain progresses through `pending_dns`, `pending_tls`, `active`, or `failed`; only an `active` domain with current TXT proof, application routing, and a valid HTTPS certificate may be assigned or used in canonical URLs. | P1 | In active platform expansion |
| DOM-5 | Requests for custom-domain links must match the active domain; the default host must not reveal an assigned link. Failed revalidation pauses delivery rather than silently falling back. | P1 | In active platform expansion |
| DOM-6 | Daily revalidation checks DNS and a no-redirect LinkPilot HTTPS challenge; token rotation returns the domain to pending DNS. Domain deletion must explicitly warn that assigned links return to default-host delivery. | P1 | In active platform expansion |
| DOM-7 | Automated certificate issuance, CNAME/provider APIs, wildcard hosts, and advanced apex guidance are available. | P2 | Catalogued / external infrastructure |

**Operational prerequisite:** DNS and reverse-proxy/application host routing must direct the host to LinkPilot. The HTTPS challenge at `/.well-known/linkpilot-domain/{token}` proves application delivery and TLS before activation.

### 7.9 Tracking pixels — In active platform expansion

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| PIX-1 | A workspace owner/admin can create an active provider pixel in the workspace. | P1 | In active platform expansion |
| PIX-2 | Supported P1 providers are Meta Pixel and Google Analytics with validated IDs. | P1 | In active platform expansion |
| PIX-3 | Pixels attach to LinkPilot-rendered link pages and CTA overlays. | P1 | In active platform expansion |
| PIX-4 | Public React emits fixed provider-specific script shapes only after explicit visitor consent; arbitrary snippets and Google Tag Manager are rejected. | P1 | In active platform expansion |
| PIX-5 | Pixel attachment to bio pages, event-specific triggering, server-side conversion APIs, and consent-mode integrations are eligible after provider and privacy review. | P2 | Catalogued |

### 7.10 Analytics and events — In active platform expansion

#### Event taxonomy

| Event | Source | When it occurs |
|---|---|---|
| `link.clicked` | Direct/overlay short link | Final outbound redirect is issued after consent. |
| `overlay.view` | CTA overlay | Eligible overlay is rendered after consent. |
| `link_page.view` | Link page | Published page is rendered after consent. |
| `link_page.clicked` | Link-page item | Visitor selects an active item after consent. |
| `bio_page.view` | Bio page | Published bio page is rendered after consent. |
| `bio_page.clicked` | Bio button | Visitor selects an active item after consent. |

#### Requirements

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| ANL-1 | Each consented analytics event has an immutable UUID, exact content references, UTC occurrence time, coarse referrer/browser/platform, HMAC visitor hash, and hash-key version. | P1 | In active platform expansion |
| ANL-2 | Never persist raw IP address, full user agent, access code, secret, password hash, or destination URL in analytics. | P1 | In active platform expansion |
| ANL-3 | Workspace and link views support fixed UTC 7, 30, and 90 day windows. Historical short-link totals and daily series continue to use the existing `clicks` table; no historical event backfill occurs. | P1 | In active platform expansion |
| ANL-4 | Views show total short-link clicks, consented unique visitor count, zero-filled daily short-link clicks, event totals, and top referrers/browsers/platforms. | P1 | In active platform expansion |
| ANL-5 | Analytics respects active workspace and selected-resource authorization; telemetry failure never blocks public delivery. | P1 | In active platform expansion |
| ANL-6 | Public rendered surfaces request a 180-day granted/denied tracking cookie before rich events or pixels. Production requires `ANALYTICS_HASH_KEY`, a key version, and 90-day event retention with daily pruning. | P1 | In active platform expansion |
| ANL-7 | Geographic rollups, live traffic, CSV export, custom events, attribution models, and consent-mode integrations are available. | P2/P3 | Catalogued / policy prerequisite |

### 7.11 Workspaces and invitations — In active platform expansion

#### Roles

| Role | Content | Active integration association | Integration records | Members | Billing | P1 workspace deletion |
|---|---|---|---|---|---|---|
| Owner | Manage | Select | Manage | Grant/change admin or member; remove non-owner | Manage | Unavailable |
| Admin | Manage | Select | Manage | Invite/change/remove members only | View | Unavailable |
| Member | Manage | Select | View | View | View | Unavailable |

“Select” means a member can attach an already-active workspace domain, overlay, or pixel to content, but cannot create, edit, activate, deactivate, or delete the integration itself. There is no separate integration-administrator role.

#### Requirements

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| WKS-1 | Every user receives a personal workspace and active-workspace pointer. | P1 | In active platform expansion |
| WKS-2 | Existing links, folders, and bio pages are backfilled to the owner's personal workspace without losing `user_id` creator data. | P1 | In active platform expansion |
| WKS-3 | Users can create and switch among valid member workspaces; an invalid pointer falls back to the personal workspace, then a remaining membership, and persists that repair. | P1 | In active platform expansion |
| WKS-4 | Owners can invite `admin` or `member`; admins can invite only `member`; neither role can invite `owner`. | P1 | In active platform expansion |
| WKS-5 | Invitations use the configured Laravel mailer and a one-time copyable acceptance URL, expire after seven days, and can be listed, resent, revoked, or replaced for the same pending email. | P1 | In active platform expansion |
| WKS-6 | Invitation acceptance requires an authenticated non-suspended user whose normalized email matches the invitation, consumes the token atomically, and creates exactly one membership. Existing members cannot be reinvited. | P1 | In active platform expansion |
| WKS-7 | Owners can change/remove admins or members except themselves; admins can change/remove only members. The final/only owner is immutable in P1. | P1 | In active platform expansion |
| WKS-8 | A non-member cannot forge an active workspace or access its resources. P1 has no workspace deletion or ownership transfer. | P1 | In active platform expansion |
| WKS-9 | Ownership transfer, guest/viewer roles, SSO/SCIM, workspace archival/deletion, and audit-log exports are available. | P3 | Catalogued |

### 7.12 Plans, billing, and usage — In active platform expansion / external payment prerequisite

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| BIL-1 | Plans define active limits for links, domains, pixels, members, webhooks, and link pages. | P1 | In active platform expansion |
| BIL-2 | Every workspace has one `active` local subscription state and a default free entitlement. | P1 | In active platform expansion |
| BIL-3 | The billing page shows plan, per-resource usage, limits, and overage-safe creation behavior. | P1 | In active platform expansion |
| BIL-4 | Metered creation rejects before exceeding the current plan's non-null limit. Existing data remains usable after a downgrade. | P1 | In active platform expansion |
| BIL-5 | Platform administrators can assign a plan to a workspace; a user can select only free for their own workspace. | P1 | In active platform expansion |
| BIL-6 | Credit-card checkout, payment-method changes, cancellation/renewal, invoices, tax, and provider webhooks work through Stripe/PayPal. | P3 | External prerequisite |
| BIL-7 | Usage invoices, proration, coupons, annual billing, and dunning are available. | P3 | Catalogued / external prerequisite |

| Plan | Price | Links | Domains | Pixels | Members | Webhooks | Link pages |
|---|---:|---:|---:|---:|---:|---:|---:|
| `free` | USD 0.00 | 25 | 0 | 0 | 1 | 0 | 1 |
| `pro` | USD 29.00 | 500 | 3 | 10 | 5 | 5 | 25 |
| `business` | USD 99.00 | unlimited | unlimited | unlimited | unlimited | unlimited | unlimited |

`null` means unlimited. Links, domains (including pending), pixels, webhooks, and link pages count whether active or inactive; members include the owner; unexpired pending invitations reserve a member slot. The plan update and creation check lock the workspace. Downgrades never delete, disable, or unpublish existing records: they block new creation of the over-limit resource until deletion, revocation/expiry, or plan upgrade returns the workspace to compliance.

**External prerequisite for BIL-6:** merchant account, products/prices, API keys, webhook secret, tax/invoice policy, and a payment-data compliance owner.

### 7.13 Webhooks — In active platform expansion

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| WHK-1 | A workspace owner/admin can create, update, disable, delete, and rotate a webhook endpoint for selected event names. | P1 | In active platform expansion |
| WHK-2 | Creation and every delivery attempt require HTTPS and fresh public-address validation; private, loopback, link-local, reserved, unresolved, and redirected targets are rejected. | P1 | In active platform expansion |
| WHK-3 | Each persisted consented analytics event creates a delivery record and queued job. | P1 | In active platform expansion |
| WHK-4 | The immutable P1 envelope includes `schema_version: 1`, UUID `event_id`, event name, UTC occurrence time, workspace ID, and a minimal relevant resource-data object. The exact serialized bytes are signed and reused across retries. | P1 | In active platform expansion |
| WHK-5 | Delivery records include webhook revision, attempted count, `pending`/`delivered`/`failed`/`cancelled` state, response code, bounded response excerpt/failure detail, and completion time. | P1 | In active platform expansion |
| WHK-6 | A delivery has three total attempts: initial, then 60-second and 300-second retries; only 2xx succeeds. The client has a 3-second connect and 10-second total timeout and does not follow redirects. | P1 | In active platform expansion |
| WHK-7 | Updating, disabling, deleting, or rotating a webhook cancels its pending deliveries while preserving history. A generated 32-byte secret is encrypted and revealed only on create/rotate. | P1 | In active platform expansion |
| WHK-8 | Manual redelivery, endpoint health reporting, IP allow lists, and inbound webhook handling are available. | P2 | Catalogued |

Every attempt resolves the URL again, rejects every unsafe answer, and forces the connection to a validated address while retaining TLS hostname validation. Payloads never include raw visitor data, destination URLs, access codes, hashes, or secrets.

#### P1 delivery envelope

The exact transmitted JSON preserves this key order and uses unescaped Unicode/slashes:

```json
{
  "schema_version": 1,
  "event_id": "uuid",
  "event": "link.clicked",
  "occurred_at": "2026-10-19T12:00:00Z",
  "workspace_id": 123,
  "data": {
    "link": {"id": 456, "slug": "launch"}
  }
}
```

`data` adds only the relevant page, item, bio page, bio link, or overlay identifier. `X-LinkPilot-Event` repeats `event`; `X-LinkPilot-Signature` is `sha256=` plus HMAC-SHA-256 over the exact body bytes using the webhook secret. The same UUID, exact body, and signature are reused for every retry.

### 7.14 API keys — Catalogued

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| API-1 | Workspace owners can create, name, scope, expire, revoke, and view last use of API keys. | P2 | Catalogued |
| API-2 | API keys are shown once, stored as hashes, and never returned in subsequent payloads. | P2 | Catalogued |
| API-3 | A versioned API exposes links, pages, analytics, and webhooks with rate limits and audit logs. | P2 | Catalogued |

### 7.15 Platform administration — In active platform expansion / catalogued depth

| ID | Requirement | Priority | Status |
|---|---|---:|---|
| ADM-1 | Platform access requires explicit `users.is_admin`; no self-service path grants it. | P1 | In active platform expansion |
| ADM-2 | An admin dashboard lists user/workspace metadata, account status, plans/subscriptions, and aggregate counts without granting non-member access to content destinations or visitor dimensions. | P1 | In active platform expansion |
| ADM-3 | A platform admin can suspend/unsuspend another account and grant/revoke another admin. Suspending clears database sessions and remember tokens but does not disable public workspace content. | P1 | In active platform expansion |
| ADM-4 | An admin cannot self-suspend or self-demote. | P1 | In active platform expansion |
| ADM-5 | `php artisan linkpilot:grant-admin {email}` is an operations-only provisioning route. | P1 | In active platform expansion |
| ADM-6 | Role/permission matrix editor, global insight dashboards, localization, site, system, SEO, email, auth, GDPR, and analytics settings exist. | P3 | Catalogued |

## 8. Data model reference

| Entity | Ownership | Key data | Notes |
|---|---|---|---|
| User | Platform | admin/suspended/current workspace | Suspension revokes authenticated sessions. |
| Workspace | User owner | name, slug | Boundary for all collaboration data; no P1 deletion. |
| Workspace membership | Workspace + user | role | One immutable owner; unique pair. |
| Workspace invitation | Workspace | email, non-owner role, hashed token, expiry/revocation | Raw token is sent/shown once. |
| Link | Workspace + creator | slug, destination, controls, optional active domain/overlay | Existing creator `user_id` stays. |
| Folder | Workspace + creator | name | Delete clears links, never deletes them. |
| Bio page | Workspace + creator | handle, display/publish state | Public handle remains globally unique in its namespace. |
| Link page/item | Workspace | published page and ordered destinations | Page slug is unique only in `/pages`. |
| Overlay | Workspace | content and active state | Short-link interstitial. |
| Custom domain | Workspace | hostname, token, lifecycle/TLS state | Assignment requires active DNS + TLS proof. |
| Tracking pixel | Workspace | Meta/GA provider and validated ID | No raw customer JavaScript. |
| Analytics event | Workspace | UUID/references/time/coarse dimensions | Consented, privacy-minimal, 90-day retention. |
| Plan/subscription | Workspace | limits and local entitlement state | Downgrade preserves existing data. |
| Webhook/delivery | Workspace | revisioned signed endpoint/events/log | Queue-backed and SSRF-hardened. |

## 9. Cross-cutting security and privacy requirements

- Form requests verify active-workspace ownership for every referenced folder, domain, overlay, pixel, page, item, or webhook.
- Policies check workspace membership, role, and platform admin separately.
- Secrets use Laravel encryption/hashing appropriate to whether they must be read again (webhook secret: encrypted; access code/invite token/API key: hashed).
- Do not expose access codes, hashes, raw visitor identity, destination URLs, or webhook secret in Inertia props, logs, or webhook payloads.
- Rich analytics and third-party pixels require an explicit granted consent cookie; a denied/unset choice emits neither. The consent surface links to `PUBLIC_PRIVACY_URL`; production keeps rich tracking disabled until that URL, `ANALYTICS_HASH_KEY`, and key version are configured.
- Analytics key rotation only affects new events, and 90-day retention is pruned daily.
- Validate URLs against allowed schemes. Webhooks resolve and validate every delivery attempt, pin a validated public address, retain TLS hostname validation, and never follow redirects.
- `APP_URL` plus explicit `LINKPILOT_DEFAULT_HOSTS` are the only default public hosts; unknown hosts return 404. `nullOnDelete` optional associations preserve content. Domain deletion requires an explicit warning because it restores default-host delivery; failed domain verification pauses delivery instead.
- Public host, published state, active state, schedule, access-code version, and access-code rate limit validation happen before redirect/click side effects.
- All mutation paths require CSRF/session authentication or future scoped API authentication.
- Public UI uses labels, error text near controls, visible keyboard focus, and no hover-only actions.

## 10. Delivery roadmap

| Slice | Outcome | Prerequisites | Exit criteria |
|---|---|---|---|
| P0 — Publishing foundation | Links, folders, controls, QR, basic bio | Existing Laravel/Inertia stack | Delivered and regression tested. |
| P1A — Collaboration control plane | Workspaces, roles, invitations, admin foundation, plans/usage | Safe expand/backfill migration | Existing user data survives and collaboration is authorized. |
| P1B — Branded delivery | Domains, link pages, overlays, pixels | P1A workspace ownership | Public host/surface behavior is security tested. |
| P1C — Insight and automation | Rich analytics, events, webhooks | P1A/P1B event model and queue worker | Events are private, scoped, signed, retrying, and inspectable. |
| P2 — Campaign depth | Tags, UTM/preview, direct QR, advanced bio, API keys, redelivery | P1 stable | Each module has a product owner and real workflow. |
| P3 — Monetization/platform operations | Payment provider, invoices/tax, system config, SSO/SCIM, compliance analytics | Merchant/legal/ops prerequisites | External integrations are credentialed and tested in their real sandboxes. |

## 11. Verification requirements

Every completed slice must provide all applicable evidence:

1. A failing feature test exists before production code and fails for the intended missing behavior.
2. Focused behavior tests prove authorization, valid action, invalid/forged input, and public side-effect ordering.
3. The complete Laravel suite passes.
4. `npm run build` passes after React/route additions.
5. Migrations run against the persistent local SQLite database without data loss.
6. A browser smoke scenario exercises the real changed public/dashboard surface at `http://localhost:8000`.
7. Queue-backed features have a running worker and an exercised delivery path.
8. README/operational docs explain required host/DNS/payment/worker setup and any external prerequisite.

## 12. Open decisions and triggers

| Decision | Why it remains open | Trigger |
|---|---|---|
| Payment provider | No merchant credentials, price catalog, or tax policy exists. | Product owner supplies Stripe/PayPal account and billing policy. |
| Regional privacy policy | P1 defines an opt-in mechanic, hash rotation, and 90-day retention; legal copy and regional collection basis remain operator-owned. | Privacy owner supplies policy URL/copy and any regional constraints before enabling production tracking. |
| DNS/certificate automation | P1 verifies current TLS and routing but does not issue certificates or manage DNS. | Hosting architecture and certificate operator are chosen. |
| API scope | API needs stable resource semantics and abuse controls. | A real automation consumer provides use cases. |
| Advanced bio/media embeds | Provider terms and content policy affect support. | Creator workflow prioritization and embed policy are approved. |
| RBAC depth | Owner/admin/member covers P1 without pretending enterprise RBAC. | Customer needs guest/viewer/custom roles or audit exports. |
