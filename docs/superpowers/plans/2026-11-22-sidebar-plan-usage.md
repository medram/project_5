# Sidebar plan usage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show current workspace usage from the sidebar plan item and expose the billing page through an owner-aware action.

**Architecture:** Extract the current usage ordering, formatting, icons, and row component from the billing page into a shared billing UI module. Add a small sidebar component using the existing Radix Popover primitives; use globally shared workspace usage, with pointer, keyboard, and touch interaction and an existing billing-page link.

**Tech Stack:** Laravel + Inertia + React 19 + TypeScript + Tailwind CSS v4 + existing shadcn/Radix components.

**Spec:** `docs/superpowers/specs/2026-11-22-sidebar-plan-usage-design.md`

## Global Constraints

- Match `docs/DESIGN.md`: semantic theme tokens, sidebar row styling, compact white surface, thin border, and 8 px action radius.
- Reuse globally shared `workspace.current.usage`; do not add usage APIs or duplicate billing calculations.
- Only workspace owners see `Upgrade plan`; other roles see `Plan details` and server billing permissions remain authoritative.
- Do not add dependencies or initiate checkout from the sidebar.

## Review Focus

- Hover opens the panel and moving the pointer from the trigger to the panel does not dismiss it; verify with a pointer scenario.
- Keyboard focus opens the panel, the action is reachable from the trigger, and Escape dismisses it; verify with a keyboard scenario.
- Touch/click opens the panel without navigating; verify with a touch viewport.
- Owner versus admin/member gets the correct action label; verify both roles.
- All 11 metrics preserve exact used/limit values, including unlimited limits and formatted retained-media storage; compare the panel with Billing & usage.

---

### Task 1: Share the existing billing usage rows

**Files:**
- Create: `resources/js/components/billing/usage-metrics.tsx`
- Modify: `resources/js/pages/billing/index.tsx`

**Interfaces:**
- Produces: `usageOrder`, `UsageRow`, and `formatBytes` exports used by both the billing page and sidebar panel.
- `UsageRow` accepts the existing `UsageMetric`, noun, and `keyof PlanLimits` props; preserve current billing-page output and over-limit messaging.

- [ ] Move the usage metric order, icons, byte formatter, and `UsageRow` from `billing/index.tsx` into `components/billing/usage-metrics.tsx`.
- [ ] Replace the local definitions in `billing/index.tsx` with imports; preserve plan feature limit formatting by importing the shared byte formatter.
- [ ] Confirm the new component exports retain the current labels, storage formatting, progress, and over-limit message behavior through the later focused check and billing page visual review.

### Task 2: Add the sidebar usage panel and document the interaction

**Files:**
- Create: `resources/js/components/sidebar-plan-usage.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `docs/DESIGN.md`

**Interfaces:**
- `SidebarPlanUsage` accepts the current workspace plan, role, and usage values; no backend payload changes.
- `app-sidebar.tsx` renders `SidebarPlanUsage` only when a current workspace exists.

- [ ] Implement the plan row as a keyboard-focusable and touch-operable Popover anchor; open on mouse/pen hover and focus, preserve the open state while the pointer enters the panel, allow click/tap, and close on Escape/outside interaction.
- [ ] Render the shared 11 usage rows in a responsive two-column `Usage` panel with the existing downgrade note and accessible progress values.
- [ ] Render `Upgrade plan` for owners and `Plan details` for other workspace roles; both route to the existing billing page. Keep checkout and authorization behavior on that page.
- [ ] Replace the current sidebar billing link with the new panel trigger without removing access to billing details.
- [ ] Update the sidebar footer rule in `docs/DESIGN.md` to describe the usage panel and role-aware billing action.

### Task 3: Verify the rendered interaction

**Files:**
- No new automated test files unless a direct failure shows an uncovered regression case.

- [ ] Run `npm run types:check` and `./node_modules/.bin/vp check resources/js/components/sidebar-plan-usage.tsx resources/js/components/billing/usage-metrics.tsx resources/js/components/app-sidebar.tsx resources/js/pages/billing/index.tsx` once after implementation.
- [ ] Open the authenticated dashboard in the browser. Verify pointer hover, pointer transfer into the panel, touch/click activation, keyboard focus/ArrowDown/Escape, the owner and non-owner action labels, all 11 values, null limits, and retained-media formatting.
- [ ] Verify the `Upgrade plan`/`Plan details` action opens Billing & usage and that billing checkout remains governed by the existing billing page state.
