# Admin payment provider tabs — design

## Intent

Help platform administrators manage Stripe and PayPal without scrolling through both credential forms at once. Keep shared billing policy and provider readiness visible.

## Goals

- Put the Stripe and PayPal setting groups behind accessible, keyboard-operable tabs, with Stripe selected initially.
- Keep billing availability, checkout policy, readiness, error summary, and the sticky save bar outside the tabs.
- Keep the Stripe connection test with Stripe settings.
- Preserve drafts when switching providers; saving still submits all visible changes through the existing settings route.
- Preserve current field order, secret masking, validation, permission checks, and responsive layout.

## Non-goals

- No backend, settings schema, route, validation, permission, or payment-provider behavior changes.
- No global visual redesign or new dependency.

## Approaches

1. **Provider tabs (chosen):** shared controls remain visible; Stripe and PayPal configuration switch in one compact area. This follows the request and keeps a single provider form in view.
2. **Provider accordions:** both configurations remain independently expandable. This preserves side-by-side discovery, but an open pair recreates the long page and does not establish one active provider.

## Constraints and implementation

Use the existing LinkPilot color, shape, and spacing tokens and the page-local tab pattern; implement tab semantics and arrow-key navigation without adding a primitive or dependency. Keep form values in the existing section-level state so switching panels cannot discard drafts. When validation-summary navigation targets a field in the inactive provider, activate that provider before focusing the field. Keep provider readiness global; it represents both integrations.

## Acceptance

Stripe and PayPal controls remain reachable by pointer and keyboard at 1440, 1024, 768, and 390 px. The active provider panel is announced as a tab panel; arrow keys move between tabs; field errors can activate and focus the owning provider. Shared controls, read-only behavior, Stripe test action, and save behavior remain intact. No page-level horizontal overflow at 390 px.

- Stripe connection test failures remain announced outside provider panels when another provider tab is selected.

## Follow-up: provider marks and route migration

Keep `subscriptions` as the internal settings key; distinguish it from the public route key `payment-gateways`. Update every page and settings action URL to the new slug. Do not preserve `/admin/settings/subscriptions` as an alias; `/admin/subscriptions` remains the separate subscription-management surface.

Use inline monochrome Stripe and PayPal SVG marks with `currentColor`; keep visible text labels and mark the SVGs decorative to assistive technology. No icon dependency or global theme change.

Acceptance: the new settings route and its save, secret, asset, and Stripe-test actions resolve to the existing `subscriptions` settings; the old settings slug returns 404. Provider tabs visibly show both marks and retain accessible text names.
