# LinkPilot — Initial Product Design

> Working name selected for this build. The product is feature-equivalent to the supplied reference where stated, but uses original code, copy, branding, and visual assets.

## Intent

LinkPilot is a focused link-management product for creators and small marketing teams. Its first job is to let an account owner turn a destination URL into a shareable short link, observe actual visits, and maintain a small publishable link-in-bio page from the same dashboard.

The central success moment is: create a link, share it, open the short URL, and see the click reflected in the owner’s dashboard. A bio page is included because it reuses the same outbound-link ownership and publishing model and gives the product a second public surface.

## Goals

1. Session-authenticated users can register, sign in, and sign out.
2. A user can create, list, edit, deactivate, and delete only their own short links.
3. A public short-link request records a click and redirects to the configured HTTP(S) destination.
4. The dashboard presents total click counts and recent daily click activity for each owned link.
5. A user can publish one or more bio pages with a unique handle, display name, optional bio, and ordered outbound buttons.
6. A public bio page exposes only its owner’s active links when published.
7. The product works with keyboard navigation and responds cleanly from narrow mobile screens through desktop dashboards.

## Explicit Non-Goals for This Release

- QR-code generation or downloads.
- Folders, tags, team workspaces, custom domains, billing, quotas, and role management.
- Retargeting, CTA overlays, tracking pixels, password protection, schedules, splash pages, or deep-routing rules.
- OAuth/social login, email verification flows, file uploads, avatars, and third-party link metadata previews.
- Visitor fingerprinting, location/device/browser analytics, or raw IP storage.
- Drag-and-drop bio-page widgets; ordering is controlled with explicit move actions.

## Stack and Architecture

- Laravel with the standard session-authentication flow.
- React rendered through Inertia, built with Vite and styled with Tailwind CSS.
- SQLite is the default local database; schema and application code remain database-portable through Laravel migrations and Eloquent.
- Server-owned business rules, authorization, redirect tracking, and persistence live in Laravel. React owns dashboard interaction, forms, dialog state, and responsive presentation. No separate REST API is introduced for this first-party web application.

Core records:

- `users`: Laravel-authenticated owners.
- `links`: `user_id`, unique lowercase `slug`, `destination_url`, optional `title`, active state, timestamps.
- `clicks`: `link_id`, `occurred_at`. A click stores no IP address or user-agent in this release.
- `bio_pages`: `user_id`, unique lowercase `handle`, `display_name`, optional `bio`, published state, timestamps.
- `bio_links`: `bio_page_id`, `title`, `destination_url`, `position`, active state, timestamps.

Identifiers use ASCII lowercase letters, digits, and hyphens. Short-link slugs are 4–32 characters; bio handles are 3–32 characters. An owner may supply a custom available slug or accept an automatically generated seven-character slug. All identifiers are canonicalized to lowercase before validation and storage.

Public routes are intentionally distinct: `/{slug}` handles short-link redirects, and `/bio/{handle}` renders published bio pages. Dashboard, authentication, and `bio` are reserved words and cannot be chosen as short-link slugs.

## Core Workflows

### Short links

From the dashboard, an authenticated owner enters a valid HTTP(S) destination, optional title, and optionally a custom slug. LinkPilot allocates or validates the unique URL-safe slug and shows the resulting short URL in the list. The owner can update the destination/title, toggle the link inactive, or remove it. Ownership is enforced on every management operation.

A request to an active public slug creates one `clicks` record and returns a temporary redirect to the destination. Inactive, absent, and unauthorized resources do not disclose another owner’s data.

### Insights

The links list shows each link’s total click count. A link detail/insights surface shows the total and a rolling fourteen-day daily series calculated in the application timezone, which defaults to UTC. Empty states are explicit rather than presenting invented chart data.

### Bio pages

An authenticated owner creates a page with a URL-safe handle, display name, optional bio, and publish state. They add named HTTP(S) buttons, edit or disable them, and move them earlier or later in the ordered list. A live preview uses the same public-page component/data shape as the published page. Visitors to `/bio/{handle}` see the owner’s public identity and active buttons only when the page is published.

## UX and Visual Direction

The dashboard should feel like a precise publishing console rather than a clone of the reference: a calm white/ink foundation, active-link blue (`#2563EB`), creator purple (`#7C3AED`), and a restrained pink action accent (`#EC4899`). Use Inter for operational UI and Calistoga sparingly for public bio-page identity; do not use the reference’s copy, logo, assets, or layout wholesale.

The memorable visual element is the public bio page: a full-height, responsive mobile composition with a bold identity block and clear link buttons. The dashboard stays quiet and data-first: an obvious “New link” action, readable tables, useful empty states, and one insight chart only where data exists. Forms use visible labels, inline validation, keyboard-focus states, and 44px-or-larger touch targets. Motion is limited to interaction feedback and respects reduced-motion preferences.

## Security and Data Rules

- Validate destinations as absolute `http` or `https` URLs; reject a destination whose scheme, host, and effective port equal the configured `APP_URL` origin.
- Generate collision-resistant URL-safe slugs when an owner does not provide one, and enforce case-insensitive uniqueness in the database.
- Authorize every dashboard record against the signed-in owner; public handlers expose only intended published/active data.
- Redirect with an HTTP 302 so destinations can be safely changed later.
- Aggregate clicks on the server; never render user-controlled URLs or labels as HTML.

## Acceptance Criteria

1. A new user can register, sign in, create a short link, and see it in their dashboard.
2. Visiting the generated short URL redirects to the destination and increments that link’s count exactly once for the request.
3. A user cannot list, edit, delete, or inspect another user’s links or bio pages.
4. Inactive links and unpublished bio pages are not publicly usable.
5. The insights view reports the persisted click total and recent daily values, including a clear zero-data state.
6. A user can create a bio page, add/reorder/disable buttons, preview it, publish it, and see only active buttons on its public URL.
7. The complete flow is usable at mobile and desktop breakpoints without horizontal overflow or pointer-only controls.

## Deferred Trigger

Add QR codes only after the create-link → redirect → insight loop is stable. Add folders, custom domains, teams, richer analytics, and paid-plan concerns only when the product needs multi-link organization, branded routing, collaboration, or traffic segmentation.