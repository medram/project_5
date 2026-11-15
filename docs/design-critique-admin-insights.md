# UI/UX critique: Admin insights

**Reviewed:** 2026-11-14  
**Page:** `http://192.168.100.19:8000/admin/insights`  
**Viewports:** desktop 1440 × 1000; mobile 390 × 844  
**Method:** live visual review, browser accessibility-tree inspection, and relevant component review. Filter submission behavior was not tested.

## Verdict

A coherent, restrained admin dashboard with useful privacy context and a responsive mobile layout. The main opportunities are data legibility and meaning—not a visual redesign. Fix chart readability and semantic structure first; then clarify metric scope and tidy the mobile KPI grid.

## What works

- Clear page title, persistent admin navigation, and a distinct route to visitor insights.
- The selected reporting window states UTC, and chart legends identify series and totals.
- Exact daily values are available in semantic tables behind native disclosures; empty subscription data has an explicit message.
- Privacy limits and the small-bucket threshold are stated near the relevant analytics.
- At 390 px, the page has no horizontal overflow; major dashboard panels stack in one column while the KPI and resource totals retain two-column grids.

## Findings

### High priority

1. **Small activity series are visually flattened.** The activity chart plots clicks, QR scans, and tracked events against one y-axis. In the reviewed 30-day view, clicks dominate the 0–60 scale while scans and events sit near the baseline; their daily patterns are hard to compare. Keep the exact-value table, but consider small multiples or a series toggle rather than adding a second, potentially confusing axis. Evidence: live chart; `resources/js/components/admin/insights/daily-chart.tsx:70-111`. The visible totals were 1,038 clicks, 98 scans, and 141 tracked events.

2. **Dashboard card titles are not headings.** The dashboard content has no section-level headings: “Platform insights” is its only heading in the main content (the app shell separately exposes “Sidebar” as an `h2`). Chart and panel titles are generic `div`s, so screen-reader users cannot navigate the dashboard by section heading. Render these titles at the appropriate heading level (normally `h2` beneath the page `h1`) while preserving their current appearance. Evidence: `resources/js/components/ui/card.tsx:34-41`; card-title usage in `resources/js/pages/admin/insights/index.tsx:190-240,243-332`.

### Medium priority

3. **The date window’s scope is unclear across the summary.** The range picker precedes the KPI row, but labels such as “Accounts” and “Workspaces” show a total alongside “8 new” without saying whether the total is all-time or window-scoped. “Resource totals” likewise does not state whether it changes with the selected window. Make the scope explicit per metric—e.g. “All-time accounts” and “New in selected window”—or visually separate snapshot totals from period metrics. This is a clarity issue in the presentation; this review does not infer backend filtering behavior. Evidence: `resources/js/pages/admin/insights/index.tsx:109-154,259-279`.

4. **The fifth KPI is stranded on mobile.** At 390 px, the five summary metrics form a two-column grid, leaving “Tracked events” alone in the left half of the last row. Let that tile span both columns on narrow screens, or choose a responsive layout that balances the final row; retain the five-column desktop layout. Evidence: live 390 px rendering; `resources/js/pages/admin/insights/index.tsx:114-154`.

5. **Subscription-mapping counts appear attached to the wrong concepts.** “0 mapped” sits beneath Provider MRR, while “0 unmapped” sits beneath Provider ARR. The layout suggests each count describes that monetary metric, though mapping status is a separate subscription concept. Group the mapping counts together and label them as subscription counts, separately from MRR/ARR. Evidence: `resources/js/pages/admin/insights/index.tsx:127-135`.

## Review limits

Visual and accessibility-tree review only. No date-range submission, keyboard-only session, or assistive-technology testing was performed. The current rendered data can change; the findings above concern the observed layout and labels, not the underlying analytics calculations.
