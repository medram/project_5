# Sidebar plan usage — design

## Intent and scope

When someone opens the plan item in the workspace sidebar, show current plan usage at a glance and make plan details easy to reach. Match the supplied compact two-column usage reference and `docs/DESIGN.md`.

## Design

Use the existing plan item as a hover/focus panel trigger, with click/tap to open on touch devices. Render the existing shared `workspace.current.usage` values; no new endpoint or duplicated usage calculation. Show all 11 metrics in two columns with used/limit labels and semantic-token progress bars. Preserve the panel for keyboard and pointer interaction, including focus visibility and Escape dismissal. Show an `Upgrade plan` action for workspace owners only; route it to the existing billing page, where checkout readiness and authorization remain enforced. Other roles retain a `Plan details` link.

## Approaches

- **Chosen — hybrid hover/focus/click panel using shared props.** Matches the requested hover behavior, works with keyboard and touch, and reuses data already sent on every dashboard request.
- Click-only disclosure: simpler, but misses the requested hover interaction.
- Fetch usage when opened: avoids rendering data until needed, but duplicates a globally shared payload and adds latency.

## Constraints and non-goals

Keep the sidebar plan row compact and follow the existing semantic color, radius, and button tokens. Do not change plan calculations, add billing endpoints, or initiate checkout from the sidebar. The billing page remains authoritative for upgrade eligibility and payment configuration.

## Verification

Type-check and lint the touched React components, then verify the sidebar interaction and usage values in the running dashboard, including keyboard focus and the owner/non-owner action labels.
