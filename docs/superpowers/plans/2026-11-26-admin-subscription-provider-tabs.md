# Admin subscription provider tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let administrators switch between Stripe and PayPal settings and manage them at the canonical `/admin/settings/payment-gateways` URL.

**Architecture:** Keep `subscriptions` as the internal settings key and add a distinct route key. Expose the route key to page navigation, resolve it to the internal key in settings controllers, and update every in-app link and action URL. Render local monochrome brand marks beside visible provider labels.

**Tech Stack:** React 19, Inertia 3, TypeScript, existing shadcn primitives, local inline SVG, Vitest via Vite+.

**Spec:** `docs/superpowers/specs/2026-11-26-admin-subscription-provider-tabs-design.md`

## Global Constraints

- Use the existing LinkPilot color, shape, and spacing tokens and the page-local tab pattern; implement tab semantics and arrow-key navigation without adding a primitive or dependency.
- Keep form values in the existing section-level state so switching panels cannot discard drafts.
- When validation-summary navigation targets a field in the inactive provider, activate that provider before focusing the field.
- Keep provider readiness global; it represents both integrations.
- Preserve current field order, secret masking, validation, permission checks, and responsive layout.
- Preserve settings data keys, validation, permission checks, and provider behavior; the explicitly requested route slug is the only server-contract change. No new dependency.

## Review Focus

1. Arrow-key navigation must keep selection and focus together, wrap between two tabs, and support Home/End — prove in the browser.
2. Each tab's `aria-controls` must reference its tab panel; inactive panel must be hidden and Stripe selected initially — assert in the focused component test.
3. A draft value must survive switching away and back — prove in the browser without saving.
4. A validation-summary link for an inactive provider must activate that tab before focusing its field — prove with a rejected invalid publishable key, then reload to confirm no setting was saved.
5. View-only access must keep tabs readable while leaving controls disabled and omitting save/test actions — assert in the focused component test.

---

### Task 1: Add accessible provider tabs to admin payment settings

**Files:**
- Modify: `resources/js/pages/admin/settings/section.tsx` — split shared groups from the Stripe/PayPal groups only for the subscriptions section; add page-local tabs, keyboard handling, tab panels, cross-tab error focus, and move the Stripe test panel into its panel.
- Test: `resources/js/pages/admin/settings/section.test.tsx` — add a subscriptions fixture and assertions for tab semantics, initial visibility, shared settings, and view-only behavior.

**Interfaces:**
- Consumes: `AdminSettingSection.groups` keys `availability`, `checkout`, `stripe`, and `paypal`; existing `values`, `canManage`, `fieldErrors`, and `TestStripePanel`.
- Produces: Stripe/PayPal tabs with matching `role="tabpanel"` IDs, selected state, roving tab stops, and ArrowLeft/ArrowRight/Home/End navigation. No exported interface or server contract changes.

- [x] **Step 1: Add a subscriptions render test** using the four existing group keys and fields. Assert shared billing/checkout headings and controls remain rendered before the tablist, the tablist contains Stripe and PayPal, Stripe starts selected, both `aria-controls` IDs have corresponding panels, the inactive panel is hidden, Stripe test errors render outside provider panels, and a view-only render omits Save changes and Test Stripe connection while retaining the tabs.
- [x] **Step 2: Implement tabs in `section.tsx`.** Keep non-subscription rendering unchanged. Keep shared groups outside the tab set; render provider groups in their panels with the existing field renderer. Use a stable local ID, active `tabIndex=0`, inactive `tabIndex=-1`, and arrow/Home/End focus-and-selection handling. Move `TestStripePanel` into Stripe. Render Stripe test errors in an alert outside both provider panels. When an error-summary entry targets an inactive provider, select it and focus/scroll to the field after the panel becomes visible. Keep all values in the existing section-level state.
- [x] **Step 3: Run the focused test** — `npx vp test resources/js/pages/admin/settings/section.test.tsx`; expected: the settings component test file passes.
- [x] **Step 4: Run required static checks** — `npm run types:check` and `npx vp check --no-fmt resources/js/pages/admin/settings/section.tsx resources/js/pages/admin/settings/section.test.tsx`; expected: both exit 0.
- [x] **Step 5: Prove the real page in the browser** at 1440, 1024, 768, and 390 px. Check tab visibility and keyboard navigation, no page-level horizontal overflow at 390 px, no console errors, and draft retention across tabs. Submit an invalid Stripe publishable key while PayPal is selected; verify the validation summary activates and focuses Stripe, then reload and confirm the original setting remains unchanged. Do not run the Stripe connection action or save valid settings.

### Task 2: Add provider marks and rename the settings route

**Files:**
- Modify: `config/platform-settings.php`, `app/Services/Settings/SettingDefinitions.php`, `app/Services/Settings/PlatformSettings.php`, `app/Http/Controllers/Admin/AdminSettingController.php`, `app/Http/Controllers/Admin/AdminSettingActionController.php`, and `routes/web.php` — separate the public route key from the internal settings key.
- Modify: `resources/js/types/admin-settings.ts`, `resources/js/pages/admin/settings/layout.tsx`, `resources/js/pages/admin/settings/section.tsx`, `resources/js/pages/admin/plans/index.tsx`, and `resources/js/pages/admin/subscriptions/index.tsx` — use the new route key and render the brand marks.
- Test: `tests/Feature/Admin/PayPalSettingsTest.php`, `tests/Feature/Admin/PlatformSettingsBehaviorTest.php`, `tests/Feature/Admin/AdminSettingActionsTest.php`, and `resources/js/pages/admin/settings/section.test.tsx` — cover new route mapping, old-slug rejection, internal data-key preservation, action paths, and decorative tab marks.

**Steps:**
- [x] Add `route_key` metadata to the payment settings definition and central internal↔route mapping helpers. Return both `key` and `routeKey` in the settings schema/navigation; map route params before settings reads, writes, secret clearing, or asset updates.
- [x] Move the Stripe test action route to `settings/payment-gateways/test`; update all internal settings links and migrate raw settings action URLs to Wayfinder helpers.
- [x] Add monochrome inline Stripe and PayPal marks to their existing tabs. Keep labels as accessible names and hide the SVGs from assistive technology.
- [x] Update backend and component assertions for the new contract; assert the prior settings slug returns 404 and that persisted settings remain under `subscriptions`.
- [x] Regenerate Wayfinder; run the focused backend and frontend tests, TypeScript and frontend lint, and inspect the new route and provider marks at 1440/1024/768/390 px. Record the unrelated Pint import-order warning and repository-wide PHPStan baseline errors in the handoff.

