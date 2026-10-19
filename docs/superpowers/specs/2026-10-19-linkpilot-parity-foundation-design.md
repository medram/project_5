# LinkPilot SaaS Parity Foundation

## Intent

LinkPilot already delivers owned short links, click totals, and basic published bio pages. The reference product is a much broader link-management SaaS. This release adds the first missing end-user workflows that improve everyday link organization, controlled publishing, and offline distribution without introducing external infrastructure.

## Reference evidence

REA analyzed the public reference application's login-loaded bundle as `ev_35f2c0f7b7e92ddbd0681abc492240f920399ab5186b5decdb31c95dee6036e9`. It parsed 123 of 123 observed scripts with zero parse failures and statically found 105 route candidates. The public demo's ordinary-user interface was also exercised after its one-click demo login.

Observed end-user capabilities:

| Area | Observed capabilities |
|---|---|
| Short links | Direct destinations, branded-domain selection, custom back-half, folders, tags, preview/title, tracking, retargeting, password, scheduling, list filtering/export, per-link analytics |
| QR codes | Direct or short-link destination, optional title, optional short-link sharing settings, tracking/retargeting/password/schedule, list/export, settings and insights |
| Link in bio | Content, appearance, and insights views; links, images, text, social icons, pinning, scheduling/disable controls, and YouTube/SoundCloud/Vimeo/Spotify/Twitch/TikTok embeds |
| Analytics | Time-range filter, engagements and tracked events, devices, referrers, locations, browsers, and platforms |
| Organization and delivery | Folders, custom domains, CTA overlays, tracking pixels, and link landing pages |
| Account and collaboration | API keys, notifications, billing, workspaces, members/invites, webhooks, pricing, and usage limits |
| Platform administration | Roles, users, plans/subscriptions, localization, site/system/SEO/email/auth/GDPR/analytics configuration and administrator insights |

The reference's browser scenario tool timed out at the MCP transport layer, so REA did not claim to perform that click. The authenticated user-surface observations were performed separately through the browser automation layer. The REA static feature inventory remains the authority for the broader route/module catalog.

## Current gap

LinkPilot currently has only `/links`, per-link 14-day aggregate click insights, and basic `/bio-pages`. It has no folder organization, link availability controls, password gate, QR distribution surface, custom domains, tracking/retargeting, advanced bio blocks, workspace/integration/billing, or platform administration.

## Release scope: distribution foundation

This release implements these three coherent, high-value end-user workflows:

1. **Folders** — users can create, rename, and remove private folders; a link can belong to one owned folder. Removing a folder keeps its links and clears their folder assignment.
2. **Controlled short-link access** — a link can have an optional availability window and optional access code. Links outside the active window are indistinguishable from missing public links. A protected link shows a password form until the code succeeds for that browser session. Redirects and click records occur only after all access checks pass.
3. **QR distribution** — every owned short link is available on a QR-codes screen and has an owner-authorized SVG endpoint. The QR data encodes that link's canonical public LinkPilot URL. The server generates SVG locally; no remote QR service receives link data.

## Explicit non-goals for this release

- Standalone direct-destination QR records, visual QR customization, scan analytics, and QR-specific settings.
- Custom-domain DNS verification, CTA overlays, pixels/retargeting, link landing pages, and visitor fingerprinting.
- Bio widgets, visual themes, social embeds, and bio-page analytics.
- Workspaces, invitations, API keys, webhooks, billing, plans, roles, and administrator configuration.
- Copying reference branding, routes, wording, assets, or implementation.

Those remain a staged roadmap rather than being silently dropped. The next product slices should follow the reference grouping above: analytics and bio editor depth; delivery integrations/domains; then collaboration, billing, and administration.

## Data and security rules

- `folders.user_id` owns every folder. A link assignment must reference a folder owned by the authenticated user.
- `links.folder_id` is nullable and uses `nullOnDelete()` so folder deletion never deletes links.
- `links.access_code` stores only a Laravel hash. It is never serialized to Inertia props, logs, or browser storage.
- A protected-link session grant is keyed by link ID. It expires with the normal server session and never applies to another link.
- `available_from` and `expires_at` are nullable UTC timestamps. If both exist, start must be earlier than expiration.
- `is_active`, scheduling, and access-code checks all happen before redirect/click recording. Any unavailable public link returns HTTP 404 and produces no `clicks` row.
- QR SVG generation uses `endroid/qr-code`; the QR request remains protected by LinkPilot ownership authorization.
- All new dashboard UI uses the repository's local shadcn/Radix primitives and existing Inertia conventions.

## Acceptance criteria

- An owner can manage folders, assign their links to folders, and remove a folder without deleting associated links.
- A protected link cannot redirect or record a click before its correct access code is submitted; a successful same-session retry redirects and records exactly one click.
- Future, expired, and inactive links return 404 without creating click rows.
- Owners see only their own QR entries and can retrieve a valid SVG for their own links; another authenticated user receives 403.
- Relevant feature tests, the complete Laravel test suite, and the production frontend build pass.
- The existing Compose server remains usable at `http://localhost:8000` after migration and build.
