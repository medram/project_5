# Project surfaces

Project: LinkPilot. Replace this file for a different product. Each section
names the entry files, the data contract, the shared components to reuse, and
what not to rebuild.

## Short links

- Entry: `resources/js/pages/links/index.tsx`,
  `resources/js/components/links/link-form-dialog.tsx`,
  `resources/js/components/links/link-form-settings-dialogs.tsx`,
  `resources/js/components/links/link-form-data.ts`.
- Data: `LinkPayload`, `LinkListItem`, and `LinkIndexProps` in
  `resources/js/types/links.ts`; `LinkFormData` in
  `resources/js/components/links/link-form-data.ts`. `links` is a
  `PaginatedCollection<LinkPayload>`.
- Reuse: `PageHeader`, `StatCard` (account-scoped only), `ResourceListToolbar`,
  `ResourceListFilterChips`, `ResourceListViewToggle`,
  `ResourceListBulkTray`, `ResourceListPagination`, `FormErrorSummary`,
  `FieldError`, `Dialog`.
- Rules: list is a semantic `<ul>` grid, not a table. Create/edit is one modal
  with settings panels, not separate pages. Preserve the current list query in
  edit links and after mutations.
- Do not rebuild: destination preview, slug rules, tag creation, bulk actions,
  or the revision dialog. Extend the existing dialog.

## QR campaigns

- Entry: `resources/js/pages/qr-codes/index.tsx`,
  `resources/js/components/qr-codes/qr-code-form-dialog.tsx`,
  `resources/js/components/qr-codes/qr-code-settings-dialogs.tsx`,
  `resources/js/components/qr-codes/qr-scan-insights.tsx`,
  `resources/js/components/qr-codes/qr-code-form-data.ts`,
  `resources/js/components/qr-codes/qr-code-status.ts`.
- Data: `QrCodeCampaign`, `QrCodeAnalytics`, and `QrCodeInsightsProps` in
  `resources/js/types/qr-codes.ts`; `QrCodeFormData` in
  `resources/js/components/qr-codes/qr-code-form-data.ts`.
- Reuse: the same list primitives as short links; `Dialog` with a wider
  `sm:max-w-[52rem]`; the shared donut, ranked bars, and country map for scan
  insights.
- Rules: preview is generated from a draft payload before save; updates send
  `_method: PATCH` through `post`; scan insights use the shared insight
  anatomy and never expose visitor identity.
- Do not rebuild: QR rendering, download formats, scan window control, or the
  draft/stable preview split.

## Bio pages

- Entry: `resources/js/pages/bio-pages/edit.tsx`,
  `resources/js/pages/bio-pages/index.tsx`, `resources/js/pages/bio-pages/public.tsx`;
  `resources/js/components/bio-pages/editor.tsx`,
  `resources/js/components/bio-pages/live-preview.tsx`,
  `resources/js/components/bio-pages/appearance-editor.tsx`,
  `resources/js/components/bio-pages/insights-panel.tsx`.
- Data: `BioPagePayload`, `BioContentBlockPayload`, `BioPageInsights`, and
  `BioPageEditorProps` in `resources/js/types/bio-pages.ts`.
- Reuse: `PageHeader` on the index; the editor identity header on `edit`;
  `Dialog` for block forms; the insights primitives for the Insights tab.
- Rules: three tabs — Content, Appearance, Insights. Preview is a split pane,
  not a second page. Block ordering is keyboard-accessible (Alt+Arrow), with a
  drag handle as a pointer shortcut. The public page is a separate visual
  world under `null` layout.
- Do not rebuild: media picker, block forms, theme presets, or the preview
  renderer. Public rendering must stay independent of the editor chrome.

## Link pages

- Entry: `resources/js/pages/link-pages/edit.tsx`,
  `resources/js/pages/link-pages/index.tsx`,
  `resources/js/pages/link-pages/insights.tsx`,
  `resources/js/pages/link-pages/leads.tsx`, `resources/js/pages/link-pages/public.tsx`;
  `resources/js/components/link-pages/block-form.tsx`,
  `resources/js/components/link-pages/variant-manager.tsx`,
  `resources/js/components/link-pages/lead-form.tsx`,
  `resources/js/components/link-pages/presentation.tsx`.
- Data: `LinkPage`, `LinkPageEditorBlock`, `LinkPageVariant`,
  `LinkPageInsightsProps`, and `LinkPageLeadsProps` in
  `resources/js/types/link-pages.ts`.
- Reuse: the editor identity header on `edit`; `PageHeader` plus a back-link
  row on `insights` and `leads`; `Card` for lead responses; `FormErrorSummary`
  in large forms.
- Rules: `insights` and `leads` use the standard page wrapper and keep their
  back link above `PageHeader`. Leads are decrypted server-side and must never
  be cached client-side. Variant comparison uses the control variant as the
  baseline.
- Do not rebuild: block editor, variant assignment, lead encryption, or the
  public renderer.

## Analytics

- Entry: `resources/js/pages/analytics/index.tsx`,
  `resources/js/components/analytics/analytics-dashboard.tsx`,
  `resources/js/components/analytics/traffic-sources.tsx`,
  `resources/js/components/analytics/window-picker.tsx`,
  `resources/js/components/analytics/daily-line-chart.tsx`,
  `resources/js/components/analytics/ranked-breakdown-card.tsx`,
  `resources/js/components/analytics/country-flag.tsx`.
- Data: `AccountAnalyticsProps`, `AnalyticsInsightsResult`, `AnalyticsDaily`,
  and `AnalyticsCount` in
  `resources/js/types/analytics.ts`.
- Reuse: `PageHeader`, one window control, `StatCard` KPI row, one main line
  series, `TopCountriesCard`, donut panels, ranked lists, one privacy note.
- Rules: one metric at a time via a segmented control. Range changes update the
  URL with `replace: true`. The country map is full-width and 4:3; flags come
  from the local `flag-icons` package, never a CDN or emoji.
- Do not rebuild: the range control, the map, the donut legend, or the ranked
  bar. Follow `docs/DESIGN.md` §7.2a and §7.2c.

## Admin insights

- Entry: `resources/js/pages/admin/insights/index.tsx`,
  `resources/js/pages/admin/insights/visitors.tsx`;
  `resources/js/components/admin/insights/activity-card.tsx`,
  `resources/js/components/admin/insights/dimension-panel.tsx`,
  `resources/js/components/admin/insights/donut-chart.tsx`,
  `resources/js/components/admin/insights/daily-chart.tsx`,
  `resources/js/components/admin/insights/ranked-bars.tsx`,
  `resources/js/components/admin/insights/account-health-card.tsx`,
  `resources/js/components/admin/insights/resource-totals-card.tsx`,
  `resources/js/components/admin/insights/range-picker.tsx`.
- Data: `resources/js/types/admin-insights.ts`, built by
  `app/Services/Admin/AdminInsights.php`.
- Reuse: the exact page order in `AGENTS.md` and `docs/DESIGN.md` §7.2b/§7.2c.
- Rules: one compact window control with Apply; four text-only KPI cards; one
  main line series with a metric switch; full-width country map; three donuts;
  ranked referrers plus resource totals; growth bars, account health, and
  subscription states; one privacy note at the end. K-anonymity threshold copy
  appears once, not per card.
- Do not rebuild: page order, donut collapsing, map sizing, or the activity
  card. If a chart needs a new field, add it to the service and the TypeScript
  contract first.

## Admin settings

- Entry: `resources/js/pages/admin/settings/layout.tsx`,
  `resources/js/pages/admin/settings/section.tsx`,
  `resources/js/pages/admin/settings/settings-form.ts`,
  `resources/js/pages/admin/settings/settings-form.test.ts`,
  `resources/js/pages/admin/settings/section.test.tsx`.
- Data: `AdminSettingsPageProps`, `AdminSettingSection`, and
  `AdminSettingField` in `resources/js/types/admin-settings.ts`.
- Reuse: `PageHeader` above the rail/grid; `SettingsPanelDialog` for nested
  panels; `FormErrorSummary` for request errors; the shared sticky save bar.
- Rules: the rail is a section list at `lg`, a chooser popover below it. The
  save bar is sticky at the bottom and shows saved/unsaved state with
  `aria-live`. Secret fields never echo the stored value. A version conflict
  preserves the draft and reloads the server version only after confirmation.
- Do not rebuild: schema grouping, dirty tracking, upload handling, or the
  version conflict flow.

## Auth, account, billing

- Entry: `resources/js/pages/auth/*`, `resources/js/layouts/auth-layout.tsx`,
  `resources/js/pages/account/restore.tsx`, `resources/js/pages/settings/*`,
  `resources/js/pages/billing/index.tsx`, `resources/js/pages/billing/checkout-result.tsx`.
- Data: auth page props declared per page; `Auth` in
  `resources/js/types/auth.ts`; billing types in
  `resources/js/types/billing.ts`.
- Reuse: `AuthLayout` supplies title/description from `.layout`; do not add
  `PageHeader` to auth pages. Settings pages use `PageHeader` inside
  `SettingsLayout`'s narrow column. Restore is a narrow auth-style card with
  its own `<h1>`.
- Rules: password, passkey, and 2FA flows keep their existing wiring. The
  password visibility button is in the tab order. Inline errors use
  `FieldError`; no toast for validation. Checkout result distinguishes active,
  pending, and cancelled states and never grants a plan client-side.
- Do not rebuild: passkey hooks, 2FA setup, restore flow, or billing provider
  calls.

## Public pages

- `null`-layout public entries: `resources/js/pages/welcome.tsx`,
  `resources/js/pages/content/show.tsx`, `resources/js/pages/bio-pages/public.tsx`,
  `resources/js/pages/link-pages/public.tsx`, `resources/js/pages/links/overlay.tsx`,
  `resources/js/pages/links/unlock.tsx`, `resources/js/pages/qr-codes/unlock.tsx`.
- Public-looking pages that still use `UserLayout`: `resources/js/pages/pricing/index.tsx` and
  `resources/js/pages/links/splash.tsx`. They inherit the user shell; do not
  add another shell. Splash currently nests its own `<main>` inside the shell;
  do not copy that landmark debt.
- Data: page-specific props; content pages carry sanitized HTML.
- Reuse: the `null`-layout pages own their wrapper, spacing, typography, and
  `<main>`. Shell-rendered public pages inherit the user shell.
- Rules: `null`-layout pages render `<main>` and `<Head>` without admin or
  workspace navigation. Shell-rendered pages inherit user navigation. None use
  `PageHeader`.
  Sanitized HTML is server-owned; never re-sanitize or inject new HTML on the
  client.
- Do not rebuild: the public renderer for bio/link pages or the overlay
  behavior. They are separate visual systems.

## Shared chart and feedback primitives

| Need | Component | Owner |
|---|---|---|
| Daily activity line/bar | `InsightDailyChart`, `InsightDailyBarChart` | `resources/js/components/admin/insights/daily-chart.tsx` |
| Donut + legend | `DonutChart` via `DimensionPanel` | `resources/js/components/admin/insights/donut-chart.tsx` |
| Ranked bars | `RankedBars` | `resources/js/components/admin/insights/ranked-bars.tsx` |
| Country map | `TopCountriesCard` | `resources/js/components/analytics/traffic-sources.tsx` |
| Ranked list | `RankedBreakdownCard` | `resources/js/components/analytics/ranked-breakdown-card.tsx` |
| Flags | `CountryFlag` | `resources/js/components/analytics/country-flag.tsx` |
| KPI card | `StatCard` | `resources/js/components/dashboard/stat-card.tsx` |
| Empty state | `Empty*` | `resources/js/components/ui/empty.tsx` |
| Form error summary | `FormErrorSummary` | `resources/js/components/forms/form-error-summary.tsx` |

Do not duplicate these in a page. Do not add a second chart library, a country
bar chart, or a new empty-state block.
