# LinkPilot product design — HeroUI dashboard language

**Status:** binding visual specification for the User and Admin product areas.
**References:**
[`docs/reference/hero-dashboard.png`](reference/hero-dashboard.png) (1531 × 1093, overview dashboard) and
[`docs/reference/hero-dashboard-2.png`](reference/hero-dashboard-2.png) (1905 × 1562, analytics dashboard).
**Source language:** [HeroUI Pro — Dashboard template](https://template-dashboard.heroui.pro/) (HeroUI v3 theme tokens, light theme).
**Implementation stack:** Laravel + Inertia + React 19 + Tailwind CSS v4 + shadcn/ui primitives. The component source stays shadcn; the _visual system_ is HeroUI. Do not install HeroUI.

This document is the source of truth for both the User area and the Admin area. When the image and this document disagree, the image wins for composition and this document wins for tokens, states, and accessibility. Never copy the reference's demo data or growth percentages into product UI.

---

## 1. Design intent

A calm, data-dense operations surface: light warm-neutral canvas, white content panels, one confident blue action color, generous 16 px card radii, and near-invisible borders. Hierarchy comes from type weight, spacing, and surface contrast — not from gradients, glows, colored headers, or decorative iconography.

The screen should read in three passes:

1. **Where am I?** Narrow left rail, who I am, and a plain page title.
2. **What matters now?** A horizontal row of KPI cards with one large number each.
3. **What should I inspect?** Two chart panels, then a filterable table with row actions.

Every page in the User and Admin areas is a variation of this skeleton, not a different design.

---

## 2. Canvas and layout

### Desktop (≥ 1024 px)

| Region         | Spec                                                                                                                                                                               |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sidebar        | `240 px` wide, full viewport height, background `--background` (`#f5f5f5`), 1 px right border `--border` (`#dedee0`). Flat, not inset, no floating card, no rounded outer corners. |
| Top bar        | `64 px` high, sticky, background `--background`, no bottom border unless content scrolls beneath it. `20 px` horizontal padding.                                                   |
| Content column | `max-width: 1280 px`, centered in remaining space, `20 px` horizontal padding, `16 px` top padding, `40 px` bottom padding.                                                        |
| Content rhythm | `16 px` between page header and first block; `12 px` between KPI cards; `16 px` between major sections; `8–12 px` inside cards.                                                    |
| Sidebar gap    | The content begins after the 240 px rail. At 1531 px viewport the measured content width is ≈1240 px; do not stretch cards to the viewport edge.                                   |

### Tablet / mobile (< 1024 px)

- Sidebar becomes a modal sheet, opened from the top-bar trigger. Never a squeezed permanent rail.
- Top bar keeps `56–64 px` height: menu trigger, current page label, search, notifications.
- User area keeps the fixed bottom primary nav (`5` destinations) with safe-area padding.
- KPI grid: 4–5 columns on desktop → 2 columns on mobile (keep the pair rhythm; let a fifth card span both columns).
- Chart panels stack one column below 1024 px.
- Tables switch to a card list below 1024 px when a card variant exists; otherwise allow horizontal scroll inside the panel. Never let the page body scroll horizontally.

---

## 3. Color system

HeroUI v3 light theme values, mapped to LinkPilot's CSS variables. Hex values are exact for the light theme; dark-mode equivalents follow the same semantic roles.

| Role               | Token                        | Light     | Dark      | Use                                                 |
| ------------------ | ---------------------------- | --------- | --------- | --------------------------------------------------- |
| Canvas             | `--background`               | `#f5f5f5` | `#060607` | App background, sidebar, top bar                    |
| Primary text       | `--foreground`               | `#18181b` | `#fcfcfc` | Headings, values, body                              |
| Panel              | `--card` / `--surface`       | `#ffffff` | `#18181b` | Cards, table body, inputs, popovers                 |
| Secondary surface  | `--surface-secondary`        | `#efeff0` | `#232325` | Table header, segmented track, subtle chips         |
| Tertiary surface   | `--surface-tertiary`         | `#eaeaeb` | `#262728` | Hover rows, pressed segments                        |
| Default surface    | `--default`                  | `#ebebec` | `#27272a` | Icon buttons, active nav pill                       |
| Muted text         | `--muted-foreground`         | `#686870` | `#9f9fa9` | Labels, descriptions, metadata (4.6:1 on `#ebebec`) |
| Border             | `--border`                   | `#dedee0` | `#28282c` | Inputs, rails, card outlines                        |
| Separator          | `--separator`                | `#e4e4e7` | `#212124` | Table row dividers, internal hairlines              |
| Primary action     | `--primary`                  | `#0a6fd0` | `#0a6fd0` | Primary buttons, links, focus, selected controls    |
| Primary foreground | `--primary-foreground`       | `#fcfcfc` | `#fcfcfc` | Text/icons on the primary fill (5.0:1)              |
| Brand accent       | `--chart-3`                  | `#0485f7` | `#0485f7` | Charts, brand marks, reference accent               |
| Success            | `--success`                  | `#17c964` | `#17c964` | Positive trend, live/healthy state                  |
| Warning            | `--warning`                  | `#f5a524` | `#f7b750` | Paused, attention                                   |
| Danger             | `--danger` / `--destructive` | `#ff383c` | `#db3b3e` | Destructive actions, errors, negative trend         |

**Soft fills** are derived, never hand-picked:

```css
--success-soft: color-mix(in oklab, var(--success) 15%, transparent);
--success-soft-foreground: color-mix(
    in oklab,
    var(--success) 80%,
    var(--foreground) 60%
);
--danger-soft: color-mix(in oklab, var(--danger) 15%, transparent);
--danger-soft-foreground: color-mix(
    in oklab,
    var(--danger) 70%,
    var(--foreground) 40%
);
--primary-soft: color-mix(in oklab, var(--primary) 10%, transparent);
--primary-soft-foreground: color-mix(
    in oklab,
    var(--primary) 70%,
    var(--foreground) 30%
);
```

Rules:

- **One accent.** Blue is the only interactive accent. Do not introduce violet, pink, teal, or amber as decorative accents. Amber/red/green appear only as status semantics.
- **Blue split.** The reference's `#0485f7` stays the brand/data accent. Interactive fills and small text use `#0a6fd0` so 14 px white labels and links clear 4.5:1 (5.0:1). Never put small white text on `#0485f7`.
- **No colored page headers, gradient heroes, or glowing cards.** Panels are white; hierarchy is spacing and type.
- **Charts** use an accent ramp: `--chart-1` darkest → `--chart-5` lightest. Secondary series are not a different hue family.
- **Platform accent overrides** (`html[data-platform-accent]`) remain supported: each preset remaps `--primary`, `--ring`, `--sidebar-primary`, `--sidebar-ring`, and the `--chart-1…5` ramp (light and dark variants). Never hard-code `#0485f7` in a component.
- **Text on tinted backgrounds** must use the corresponding `*-soft-foreground`, not the raw saturated color. Minimum contrast: 4.5:1 for normal text, 3:1 for ≥ 18.66 px semibold or ≥ 24 px.

### Chart ramp

```css
--chart-1: #005fb8; /* darkest; primary series */
--chart-2: #0a7be0;
--chart-3: #0485f7; /* accent */
--chart-4: #5bb3f7;
--chart-5: #a5d8fd; /* lightest; comparison series */
```

Two-series charts use `--chart-2` and `--chart-4`. Single-series charts use `--chart-3`. Do not use more than three series on one panel.

---

## 4. Typography

**Family:** system UI stack, matching HeroUI:

```css
font-family:
    -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue',
    'Noto Sans', Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
    'Segoe UI Symbol', 'Noto Color Emoji';
```

Instrument Sans is removed from product chrome. (The public bio-page display serif is unrelated and stays.)

| Style             | Size / line-height | Weight  | Tracking  | Use                                       |
| ----------------- | ------------------ | ------- | --------- | ----------------------------------------- |
| Page title        | `24 px / 32 px`    | 600     | `-0.02em` | `Good morning, Kate`, `Users`, `Insights` |
| Section title     | `16 px / 24 px`    | 600     | `-0.01em` | `All Employees`, chart card titles        |
| KPI value         | `24 px / 28 px`    | 600     | `-0.02em` | `$228,441`, `231,856`                     |
| Card title        | `16 px / 24 px`    | 500–600 | normal    | Chart panel headings                      |
| Body / table cell | `14 px / 20 px`    | 400     | normal    | Rows, form values                         |
| Label / KPI title | `13 px / 18 px`    | 500     | normal    | `Revenue`, field labels                   |
| Meta / table head | `12 px / 16 px`    | 500     | normal    | Emails, axis labels, chips, column heads  |
| Micro             | `11 px / 14 px`    | 500     | `0.01em`  | Rare metadata; never body copy            |

Rules:

- Numerals in KPIs, tables, and charts are `font-variant-numeric: tabular-nums`.
- Do not use monospace for product UI values. Monospace is for codes, prefixes, tokens, and copyable IDs only.
- No all-caps tracked-out section labels. Labels are sentence case, muted, and small.
- Maximum two type weights per component (400 + 500/600).

---

## 5. Shape, border, elevation

| Element                        | Radius                          | Notes                                               |
| ------------------------------ | ------------------------------- | --------------------------------------------------- |
| Card / panel / table container | `16 px` (`rounded-2xl`)         | The signature radius.                               |
| KPI card                       | `16 px`                         | Same radius as panels.                              |
| Primary / secondary button     | `8 px`                          | HeroUI field radius is 12 px; buttons read tighter. |
| Input / select / search        | `12 px` (`--field-radius`)      | One step softer than buttons.                       |
| Segmented track                | `16 px`; active segment `14 px` | Inset padding 2–3 px.                               |
| Nav item / icon button         | `8 px` / `9999 px`              | Icon buttons are circles.                           |
| Chip / badge                   | `16 px` full pill               | Height 20 px, 12 px text.                           |
| Avatar                         | `9999 px`                       | 36–40 px.                                           |

Borders are `1 px` and low contrast (`--border` for controls, `--separator` for internal dividers). Cards use a white surface, a `1 px` `rgba(24,24,27,0.06)` outline, and the HeroUI surface shadow:

```css
--surface-shadow:
    0 2px 4px 0 #0000000a, 0 1px 2px 0 #0000000f, 0 0 1px 0 #0000000f;
```

No large drop shadows, colored shadows, blur/glass, or layered elevation. Hover raises surface color, not shadow.

---

## 6. Shell anatomy

### 6.1 Sidebar (240 px)

Top to bottom:

1. **Brand**: show the configured platform logo and project name at the top of both sidebars. Use the themed link mark when the logo is missing, fails to load, or is only one pixel. In the collapsed rail, keep the mark centered and the name visually hidden but accessible.
2. **Workspace switcher**: keep workspace context below the brand in the user sidebar header.
3. **Primary navigation**: 40 px rows, `10 px` horizontal padding, `8 px` radius, `10 px` icon–label gap, 16–18 px Lucide icons, `14/500` labels. Active: `--default` background, `--foreground` text. Hover: `--surface-secondary`. No active left bar, no accent-colored active text. Optional badge: 20 px success/default pill (`New`).
4. **Sections**: `Workspace` and `Resources` for the user area; `Overview`, `Accounts`, `Content`, `Monetization`, `System` for admin. Section label: `11–12 px`, `500`, muted, `8 px` top margin, `6 px` bottom. Do not draw divider lines between sections.
5. **Footer** (pinned beneath the scrollable navigation): show the current-plan row followed by the identity block in the user area. In admin, show `Workspace app` followed by the identity block. The profile menu in both areas contains `Settings`, `Help & information`, and `Log out`. Mouse/pen hover and keyboard focus open the plan usage panel; click/tap also opens it. The two-column panel shows all 11 usage metrics, progress, and the downgrade note. Owners get `Upgrade plan`; other roles get `Plan details`. Both actions open Billing & usage, where checkout and permissions remain enforced. Log out is a real POST action.

The final identity block has a 40 px avatar (photo or deterministic soft gradient + initials), name `14/600`, and muted role `12/500`. It opens the account menu above the trigger; selecting a navigation action closes the mobile sidebar. Help opens a dialog that remains available after the menu closes and returns focus to the identity trigger when dismissed. In the collapsed 48 px rail, center the complete 32 px avatar and open its menu to the right.

### 6.2 Top bar (64 px)

- Left: sidebar trigger (`32 px` circle, ghost), then a `13/500` context label: the active workspace in the user area, `Platform administration` in admin. Nested pages replace it with the parent breadcrumb trail. The page's single display `h1` lives in content, never duplicated here.
- Right: search (`32 px` icon button; opens the command palette) and notifications (`32 px` circle with a small accent dot when unread). The page's primary action stays in the page header's action cluster, where it belongs to that page's content.
- HeroUI tertiary icon buttons use `--default` background, `8 px` radius or full circle, 16 px icon. Ghost buttons are reserved for the sidebar and toolbar.

### 6.3 Page header

- `h1` 24/600, one optional sentence of `14/400` muted description, actions right-aligned and wrapping below the title on mobile.
- Action clusters shrink and wrap their controls before the title column is squeezed. A wide filter form must never force the `h1` into a one-word column.
- No eyebrow, icon tile, breadcrumb repetition, or decorative rule above the title.
- Breadcrumbs remain available in the top bar on desktop; they are not repeated as a second title block.

---

## 7. Components

### 7.1 KPI card

```
┌──────────────────────────────────┐
│ Revenue                    [icon] │  ← 13/500 muted; icon right
│ $228,441                   [↑3.3%]│  ← 24/600 tight; context right
└──────────────────────────────────┘
```

- `16 px` padding, `12 px` gap, white surface, 16 px radius, minimum height 94 px.
- Grid: label/value left, trend or context chip right, vertically aligned to the value baseline.
- Delta chip: 20 px tall, soft success/danger fill, 12/500, 12 px arrow icon. Show only when a real comparison period exists. Otherwise show a neutral period chip (`30 days`) or no chip.
- Each KPI uses a 20 px Lucide icon in a 40 px `--primary-soft` tile. Place it at bottom right when there is no context; with a note, trend, or sparkline, place it at top right and reserve space so nothing overlaps.

### 7.2 Chart panel

- Header row: title (`16/600`) left, period select / legend / overflow menu right.
- Optional inline stat row: `18/600` value + `12/500` soft trend chip, label `12/400` muted beneath.
- Chart region: 180–240 px high, no vertical gridlines, 1 px horizontal gridlines in `--separator`, axis labels `11 px` muted, 8 px tick gap. Lines `2 px`, dots hidden, active dot 4 px.
- Legends: 12 px dot + `12/400` muted label, 6 px gap; only for multi-series charts.
- Cards align in a 2-column grid (`1fr 1fr`) with 16 px gap; stack below 1024 px.

### 7.2a Analytics composition (second reference)

The analytics screen adds three patterns on top of the overview dashboard:

- **One title contract.** The reference puts the page name (`Analytics`) in the top bar and omits a content title. LinkPilot keeps the top bar to a compact `13 px` current-page breadcrumb and renders the single `24/600` page `h1` in content, so every page (including custom admin headers) has the same title contract. Never add a second eyebrow, kicker, or duplicated display title.
- **KPI sparklines.** Each KPI card may carry a 32–40 px edge-to-edge sparkline at its bottom: 1.5 px line, soft gradient fill fading to transparent, no axes or dots. Use the metric's semantic color (blue/success/warning) only when the series is real. Cards without a series have a 94 px minimum height and may grow to fit their icon and context.
- **Section headings outside tables.** `Top pages` uses a `16/600` heading plus a `12/400` muted description above the table, not a card title. Date ranges use a quiet segmented control (`7D 30D 90D 12M`), 28–32 px high, above the KPI row.

Large analytics panels: one full-width primary time-series card, then a two-column row (donut distribution + ranked horizontal bars), then a full-width table. Stat totals inside chart cards (`116,650` + `18.3%` chip) sit above the chart, left-aligned, with the legend at the top right.

Traffic-source breakdowns extend that composition: a full-width country choropleth using the single accent ramp, followed by a three-column row of ranked referrers and browser/platform distribution cards. The map keeps a ranked legend with country names, counts, and shares; distribution legends keep every exact count and percentage visible. Charts never rely on color alone. Use the existing chart-card icons and do not add a second accent family for categorical slices.

- Countries use `--chart-2` with opacity scaled from the listed minimum to maximum; unlisted countries use `--muted-foreground` at 18% so the world outline remains legible in both themes. The map's legend includes a Fewer → More scale.
- Country maps use an unclipped Mercator canvas at its native 4:3 ratio, never a stretched or rounded map surface. Legend rows pair a decorative local SVG flag with the country name and ISO code. Flag assets are bundled through the local `flag-icons` package; never load them from a CDN.
- Distribution cards use the `--chart-1…5` ramp for up to five listed cohorts, including an `Other` cohort when the analytics service returns one. The legend repeats every count and share for keyboard and non-visual access.
- The map's internal caption is visually hidden because the card heading is the single title; the SVG retains the descriptive `aria-label`. The full-width map stacks above the three-column breakdown row below `lg` and the three cards stack on mobile. Keep `min-w-0` on the map grid and map wrapper so the responsive SVG's intrinsic width cannot widen the card past the viewport.

### 7.2b Admin platform insights composition

The platform dashboard is the operations view of the same analytics language, not a second design. Keep this order:

1. **Header.** Title and one description sentence; a compact `Window` select, `Apply`, and `Visitor insights` action at the same 36 px control height. No full-width filter bar and no duplicate range label.
2. **KPI row.** Four cards with metric icons: `Accounts`, `Workspaces`, `Provider MRR`, `Tracked events`. Do not add sparklines on this page; each card carries one figure and one context note.
3. **Daily activity.** One full-width panel, `300 px` chart, with a `Clicks / QR scans / Tracked events` segmented control. Show the selected total and daily peak above the chart. Never plot three differently scaled series against one axis and never restore an exact daily-values table.
4. **Countries.** A full-width country map with the ranked flag legend. Ranked countries use `--chart-2` opacity; the rest use `--muted-foreground` at 18%. Put the unresolved-country count in muted text below the map and keep the native 4:3 canvas unclipped.
5. **Distribution row.** Three donut cards: `Top browsers`, `Top platforms`, `Device classes`. Donuts collapse to five slices (top four named cohorts plus `Other`); the center carries the event total and each legend row repeats count and share.
6. **Referrers and resources.** `Top referrers` ranked bars beside a compact `Resource totals` definition grid. Resource totals are two-column label/value pairs, never a full-width table.
7. **Growth and account health.** `Daily growth` bar panel, `Account health`, and `Cashier subscription states` in one desktop row. Account health opens with `active / total` and a progress bar, then the independent suspended and deletion-pending flags, then subscription mapping. Do not repeat the same snapshot as a row of badges above the charts.
8. **Privacy.** One slim note at the end. State the k-anonymity threshold once below the donut row, not in every card description.

### 7.2c Insights pages standard (all scopes)

Every insights page — account, link, bio page, QR campaign, admin platform, admin visitor — uses one contract. Scope changes the copy and the data, never the layout vocabulary. New insights pages reuse the shared primitives instead of rebuilding charts.

#### Page anatomy

1. **Header.** One `h1`, one sentence that names the scope and the consent rule, and at most one secondary action. No eyebrow, no repeated title in the top bar.
2. **Window.** One control, one source of truth, UTC. Account-scoped pages place the shared picker above the KPI row; admin pages place the compact `Window` select + `Apply` in the header action cluster. Never render two range controls or repeat the selected range as decorative text.
3. **KPI row.** Three to five cards. Each card carries one figure, one label, one context note, and a metric icon. A card never holds a second chart. Sparklines are optional only on account-scoped pages and only for a real daily series; admin aggregate pages do not use them.
4. **Primary time series.** One full-width panel, `300 px` plot, total and comparison context above the plot. Use a segmented control for mutually exclusive daily metrics. Never place two differently scaled series on one axis. A second time panel is allowed only when it is visibly smaller and answers a different question.
5. **Geography.** Countries are a full-width map with a ranked flag legend. Never a country donut or a country bar list. Ranked countries use `--chart-2` opacity; unlisted countries use `--muted-foreground` at 18%. Show the listed total and, when the dimension is incomplete, the unresolved count in muted text.
6. **Distribution row.** Browsers, platforms, and device classes are donuts. Referrers, pages, buttons, event names, and UTM values are ranked bars or compact lists. Keep it three-up at `lg` and two-up at `sm`; never four equal chart cards in one row.
7. **Closing row.** Scope-specific snapshots — daily growth, account health, subscription states, resource totals — sit below the traffic breakdowns as compact cards. Resource and inventory totals use a two-column definition grid, never a full-width table.
8. **Privacy.** One slim note at the end. Consent scope, coarse dimensions, and the k-anonymity threshold appear once on the page, not in every card description.

#### Chart choice

| Question                               | Form                                      | Shared primitive                                                       |
| -------------------------------------- | ----------------------------------------- | ---------------------------------------------------------------------- |
| How does one measure change over time? | Full-width line, one series at a time     | `InsightActivityCard` / `InsightDailyChart` / account `DailyLineChart` |
| How did new items accumulate?          | Compact stacked bars, total in the legend | `InsightDailyBarChart`                                                 |
| Where did events come from?            | Full-width choropleth + ranked legend     | `TopCountriesCard` / `TrafficSourcesSection`                           |
| What share is each cohort?             | Donut, top four named cohorts + `Other`   | `DimensionDonut` / `PieBreakdownCard`                                  |
| Which items rank highest?              | Ranked bars, one accent, count visible    | `RankedBreakdownCard` / `RankedBars`                                   |
| Is one target being met?               | Progress bar with the absolute figure     | Account health pattern                                                 |

Rules for the shared charts:

- One accent per chart. The main chart uses `--chart-2`; donuts use the `--chart-1…5` ramp; ranked bars use `--chart-2` only. Never rainbow a single series.
- Donuts show a center total and a legend that repeats every count and share. Collapse to five slices so no two slices share a color.
- A map legend pairs a local flag, country name, ISO code, count, and share. Do not rely on map color alone.
- Ranked bars show the exact count; add a share only when the denominator is the full dimension and is stated.
- Dimensions that do not cover every event say so in the card footer or the page note. Never present a listed total as the page total.
- Chart tooltips keep exact daily values. Do not add an exact daily-values table to aggregate or admin insights pages. Account-scoped pages may keep a native `<details>` disclosure where auditability is required, but it must never replace the chart or the tooltip.

#### Empty, loading, and error states

- Use the shared `Empty` block with one sentence and one next step. Never invent demo rows to fill a window.
- Skeletons match the final card, chart, and table heights; do not collapse the layout while loading.
- Errors stay inside the affected card and keep the page's other sections usable.

#### Reuse map

| Concern              | Reuse                                                                                    | Do not rebuild           |
| -------------------- | ---------------------------------------------------------------------------------------- | ------------------------ |
| KPI card             | `components/dashboard/stat-card.tsx`                                                     | A per-page stat tile     |
| Range and window     | `components/admin/insights/range-picker.tsx`, `components/analytics/window-picker.tsx`   | A third window control   |
| Daily line / bars    | `components/admin/insights/daily-chart.tsx`, `components/analytics/daily-line-chart.tsx` | Recharts setup per page  |
| Country map + legend | `components/analytics/traffic-sources.tsx`                                               | A second world map       |
| Donut + legend       | `components/admin/insights/donut-chart.tsx`, `PieBreakdownCard`                          | A second pie legend      |
| Ranked lists         | `components/analytics/ranked-breakdown-card.tsx`, `ranked-bars.tsx`                      | Page-specific bar markup |
| Country flag         | `components/analytics/country-flag.tsx`                                                  | CDN flags or emoji       |
| Empty state          | `components/ui/empty.tsx`                                                                | Decorative illustrations |

#### Data honesty

- Independent flags stay independent. Suspended accounts and deletion-pending accounts can overlap; never add them into a headline total.
- A total must name its denominator: `events with a resolved country`, `listed referrers`, `active accounts`, `provider subscriptions`.
- When a dimension is thresholded, state the threshold once near the dimension group. Do not repeat `Buckets under 5 events…` in every card.
- Use the page's exact unit: `clicks`, `QR scans`, `events`, `page views`, `subscriptions`. Do not call events visitors.
- Every number is `tabular-nums`; every share uses one decimal format across the page.

### 7.3 Segmented control / tabs

- Track: `--default` (`#ebebec`), 16 px radius, 3 px padding, height 36–40 px.
- Active segment: white, 14 px radius, `0 1px 2px rgba(0,0,0,0.06)` shadow, `14/500` foreground.
- Inactive: transparent, `14/500` muted; hover text foreground. Keyboard: arrow keys move selection, roving `tabIndex`.
- Use for view switches and metric switches. Never use pill-shaped primary buttons as tabs.

### 7.4 Buttons

| Variant           | Fill        | Text                   | Border     | Height   |
| ----------------- | ----------- | ---------------------- | ---------- | -------- |
| Primary           | `--primary` | white                  | none       | 32–36 px |
| Secondary/outline | `--card`    | foreground             | `--border` | 32–36 px |
| Tertiary          | `--default` | foreground             | none       | 32–36 px |
| Ghost             | transparent | muted → foreground     | none       | 32–36 px |
| Destructive       | danger soft | danger-soft-foreground | none       | 32–36 px |

Radius 8 px, horizontal padding 12–16 px, icon gap 6–8 px, icons 16 px. Focus ring: `2–3 px` `--ring` at 30–50% opacity, offset 1 px. Disabled: 50% opacity, not-allowed. Minimum mobile touch target 44 px.

### 7.5 Inputs and filters

- Height 36–40 px, white fill, 1 px `--border`, 12 px radius, 14 px text, 12 px horizontal padding, placeholder `--muted-foreground`.
- Hover: border `--muted-foreground` at 40%. Focus: primary border + 3 px primary ring at 20%.
- Search is a rounded input with a leading 16 px search icon; on mobile it may collapse to an icon button that opens the palette.
- Filter toolbars are a row of tertiary buttons (`Filter`, `Sort`, `Columns`) with 16 px icons on the left and a search field pushed right. Do not wrap filters in a hard-bordered card unless they are part of a table panel.

### 7.5a Link creation modal

- Desktop: `bg-card` surface, 16 px radius, `1 px --border`, `sm:max-w-[40rem]`, centered. A plain header (16 px icon + `Create short link` / `Edit short link`) replaces prose copy; the labelled close control stays top-right.
- The dialog body is two regions: one compact core field group in the only vertical scrollport, and a `shrink-0` action strip separated by a `1 px --border` top border. The strip never scrolls, so `Create link` / `Save changes` is always reachable.
- Core fields, in order: destination, link host + `/` + back-half, type with its conditional target, title, then folder and tags side by side. Host and back-half are equal-width columns; their labels share a baseline and the slash centers on the controls. Put helper/error copy in a separate row beneath the controls. Optional settings live behind the strip's `Tracking`, `Retargeting`, `Password`, and `Schedule` actions, not in the core scrollport.
- Secondary settings open as 42 rem nested `bg-card` dialogs above the base dialog with the same radius, border, and field primitives. Each has its own scrollport and `Close` / `Save changes` footer; a configured action shows a soft primary state plus text, never color alone. Select/popover portals inside a child render above it.
- Footer actions pair a 16 px icon with a visible label: Tracking (`ChartNoAxesCombined`), Retargeting (`Crosshair`), Password (`LockKeyhole`), Schedule (`CalendarClock`). Retargeting uses selects, not free-text match values: a searchable country control fed by the page's ISO list, plus fixed device and platform selects. Changing a rule type clears its match value.
- Mobile: one column, stacked host/back-half, a two-column action grid with the primary action full width beneath it, and 44 px minimum interactive height. The base dialog keeps 16 px inset from the viewport edge and no page-level horizontal scroll.
- Hierarchy may follow the compact reference layout, never its dark-only palette or its raw colors: both themes use the semantic tokens above.

### 7.5b Link in bio editor

- Tabs: `Content`, `Appearance`, `Insights` as underlined tab buttons. Content and Appearance share the 1fr + 20–28 rem live-preview split; Insights takes the full content width and hides the preview so charts are not squeezed.
- Appearance controls are visual radio cards, never native selects: theme cards render a miniature identity card, typography cards render an `Aa` sample, accent colors render five focusable swatches, and background cards render their canvas. Selection is reinforced by a border, ring, and check, never color alone.
- The live preview is presented inside a 22 rem device frame with a dark frame and a scrollable screen. The preview display-name size is constrained separately from the public page so long names do not overflow the phone.
- Insights composition: a reporting-window segmented control, three KPI cards (`Page views`, `Button clicks`, `Click-through rate`), one full-width engagement line panel with `Page views` and `Button clicks` series, then a ranked top-buttons card. The traffic-source section must reuse the Link analytics components exactly: countries map, ranked referrers, browser pie, and platform pie. Do not render an exact daily-values table in the insights tab.
- Page content is a white card, never a loose header over floating rows on the canvas. The card header holds the `Page content` title, ordering help, and the two add actions; add and edit forms open inside the card on a subtle `--surface-secondary` inset.
- Content rows are hairline-separated, not separate cards. Each row pairs a Lucide type icon (link, text, image, social, embed), a truncated title, and one muted destination/type line. Link rows show the destination without its protocol and keep the full URL in the hover title. Pinned is a pin icon, hidden is quiet text.
- Row actions are 32 px icon-only ghost buttons with accessible names and tooltips: move up, move down, edit, then a `More actions` menu for link visibility and delete. Below `sm`, edit moves into the `More actions` menu so titles keep enough width. Do not repeat text-button labels for these actions. Preserve Alt + arrow-key reordering on the row.

### 7.5c QR creation modal

- The QR modal mirrors the link creation modal: `sm:max-w-[52rem]`, a fixed header, one scrollable body, and a fixed action strip. Never let the footer scroll away.
- Core fields stay minimal: destination type, destination URL or workspace short link, and an optional title. A QR linked to a short link shows its fixed path as read-only instead of destination controls.
- Desktop body is a two-column grid: core fields left, QR preview and the `Active` switch right. Below `md`, the preview stacks after the fields. Draft previews must say they are not scannable until saved; saved edits use the stable campaign preview.
- Optional fields live behind four footer actions: `Sharing`, `Style`, `Access`, and `Schedule`. `Sharing` holds the share title and description, `Style` holds colors, error correction, size, margin, and logo, `Access` holds the access code, and `Schedule` holds availability, expiry, and the scan cap. Configured actions use the soft primary state plus an updated accessible label.
- Settings panels reuse `SettingsPanelDialog` and the base modal error summary reuses `FormErrorSummary`; do not recreate dialog chrome or a second error-summary pattern. On mobile, the action strip is a two-column grid with the primary action full width, and panel footers put `Cancel` above `Save changes`.
- Validate destination URLs with the server's first-party guard. Errors must appear both inline and in a focusable, clickable summary; summary entries for a closed panel open that panel and focus the failing field.

### 7.5d QR scan insights

- Campaign scope opens directly on the compact destination card; workspace scope opens directly on the reporting-window control. Do not add a back-button row or a visible page-title block: the top-bar breadcrumbs and sidebar provide navigation, while the page title remains the document title and an `sr-only` `h1`.
- The destination card is a compact `CardHeader` strip: at desktop one row carries the inline destination label/target on the left, then campaign code, campaign type, semantic status, and `Open destination` on the right. Below `sm`, the type is hidden and the trailing controls wrap, with the link right-aligned where space allows. Do not add a separate `CardContent` row or repeat the campaign title.
- The reporting-window control reuses `AnalyticsWindowPicker` immediately after the destination card. KPI cards are `Scans`, `Countries`, `Browsers`, and `Devices`, each with the selected window as its note; only `Scans` carries a sparkline.
- The daily panel is one full-width line chart titled `Scans over time`, with one blue chart-ramp series and a series key. The scan total lives only in the `Scans` KPI; do not repeat a 24/600 total in the chart header. Do not render an exact daily-values table for this scoped view.
- Traffic breakdowns reuse `TrafficSourcesSection` with `metric="scans"`: a full-width country map and legend, then top devices, referrers, browsers, and platforms. QR copy says `scans`/`QR scans`, never consented events or visitors. Top devices are derived from the stored platform bucket.
- QR scans store coarse `country_code`, `browser`, `platform`, and `referrer_host` request dimensions only; no raw IP, cookie, visitor hash, or user agent is retained. The muted note above the breakdowns carries both the privacy statement and the legacy-coverage explanation: older timestamp-only scans still count in totals and the daily trend while dimension cards only include scans with metadata.
- Empty dimension cards use the same icons and empty states as Link insights, with QR-specific explanations: referrers usually arrive only when the browser sends one, and device data requires a recognizable user agent.

### 7.5e Admin settings

- Keep the five route-backed settings groups in a desktop rail without nested scrolling and a searchable mobile chooser. Use 36 px rows with space between groups, increasing to 40 px when the viewport is at least 1050 px tall. The rail moves with the document so every section remains reachable on short screens. Use one `Platform settings` page title, the selected section as `h2`, and schema group titles as `h3`.
- Render ordinary and secret inputs in one column each at `xl`, and full width below `xl`; switches, textareas, lists, and segmented choices span the grid. Keep the sticky save bar clear of keyboard focus, including the visible upload label.
- Show actionable provider readiness and server messages with readable semantic alert colors in both themes. Field errors appear inline and in the focusable summary; stale-version errors preserve the draft and require a reload before saving.
- Retry delays use a numeric ordered editor; menu page IDs use the searchable published-page picker and resolve selected titles without discarding unavailable IDs.

### 7.6 Table / data grid

- Container: white, 16 px radius, 1 px border, clipped overflow.
- Header: `--surface-secondary` fill, 12/500 muted labels, 40 px row height, no vertical dividers, horizontal padding 16 px. Header remains visible when the table scrolls vertically.
- Rows: 56–60 px, white, 14 px cells, `1 px --separator` bottom border, last row no border. Hover `--surface-secondary` at 50–70%. Selected row: `--primary-soft` with the primary checkbox.
- First column is the identity column: 36 px avatar + name `14/500` + email `12/400` muted stacked. Numeric columns right-aligned and tabular. Actions column right-aligned, `32 px` circular tertiary buttons; destructive action uses soft danger fill and danger icon. Always provide `aria-label` and a tooltip.
- Sortable headers use a 12 px chevron and `aria-sort`.
- Pagination sits below the container, outside the white surface: muted count left, 32–36 px page buttons right, current page primary.

### 7.7 Chips and status

- Pill, 20 px height, 12/500, 6 px horizontal padding minimum, icon 12 px.
- Default: `--surface-secondary` bg, foreground text. Success: soft success. Danger: soft danger. Warning: soft warning. Accent: soft accent.
- Status dots are 6 px and never the only signal — pair with text.
- `Live` = success, `Paused` = warning, `Archived` = default, `Pending` = warning/accent, `Suspended` = danger.

### 7.8 Avatars

- 36 px in tables, 40 px in the sidebar, `9999 px` radius, 1 px subtle ring.
- If a photo exists, show it. Otherwise initials on a deterministic soft gradient derived from the user/workspace id (blue, teal, rose, amber families at low saturation). Never use a single flat gray circle for every row.
- Gradient avatars are decorative (`aria-hidden`); the adjacent name is the accessible label.

---

## 8. Interaction and motion

- Transitions: `150 ms` for color/background/border; `200 ms` for layout/width. Easing `cubic-bezier(0, 0, 0.2, 1)` (HeroUI `--ease-out`).
- Hover changes surface or text color only. Do not scale cards or lift shadows.
- Focus is always visible and uses the primary ring; never remove it for mouse users.
- Loading: skeletons match final block dimensions (no layout shift). Buttons show an inline spinner and keep their width.
- Respect `prefers-reduced-motion`: disable non-essential transitions and chart animation.
- Toasts/dialogs use the same 16 px radius and surface tokens; overlays use HeroUI's overlay shadow, not a heavy black scrim.

---

## 9. Content and data rules

- Titles and labels are sentence case. No "Welcome back 👋" emoji, no exclamation marks.
- KPI labels name the measure, not the feature: `Clicks`, `Unique visitors`, `Tracked events`; admin: `Accounts`, `Workspaces`, `Provider MRR`, `Tracked events`.
- A KPI shows one number. Put period/comparison context in the 12 px note or chip.
- Never invent deltas. If the backend does not provide a previous-period value, omit the trend chip rather than showing `0%` or an arrow.
- Empty states explain the next action in one sentence and provide one primary action. Do not illustrate with large decorative art.
- Privacy copy stays visible near analytics (consent, coarse dimensions, no raw IPs). Do not bury it behind a tooltip.

---

## 10. Applying the language to LinkPilot

### User area

| Screen                                                                    | Reference mapping                                                                                                                                                                                                  |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Shell                                                                     | 240 px rail, pill nav, pinned plan and identity footer; help and logout in the profile menu; 64 px top bar; gray canvas.                                                                                           |
| Insights (`/dashboard/insights`)                                          | The reference dashboard: 4 KPI cards, daily engagement line panel + traffic-source panel, then dimension/table panels. Filters collapse into a toolbar row above the KPI cards.                                    |
| Short links (`/dashboard/links`)                                          | The reference table section: `Short links` heading + count chip, filter/sort/columns toolbar, search right, white table with identity column, click count, row actions. Cards view uses the same surface language. |
| QR codes / Bio pages / Link pages / Folders / Domains / Overlays / Pixels | Same skeleton: page header, optional KPI strip, toolbar, list/table surface. No bespoke card styling.                                                                                                              |
| Settings / Billing                                                        | Same shell and cards; forms use the 12 px field radius and 8 px buttons.                                                                                                                                           |

### Admin area

| Screen                                                            | Reference mapping                                                                                                                                                                                                                                                                                             |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Shell                                                             | Same 240 px rail and top bar. Admin has more sections; keep the muted section labels and 40 px rows.                                                                                                                                                                                                          |
| Platform insights                                                 | Header inline range picker. Four KPI cards with icons, one full-width daily activity chart with a metric switch, full-width country map, browser/platform/device donuts, referrers beside compact resource totals, then growth/account health/cashier states and a privacy note. No exact daily-values table. |
| Users                                                             | Reference table page: KPI strip, filter toolbar, white table with avatar identity, roles, plan, last seen, and row actions.                                                                                                                                                                                   |
| Roles / Plans / Subscriptions / Content / Files / Logs / Settings | Same surfaces, KPI strips, and table rules. Do not add new accent colors per section.                                                                                                                                                                                                                         |

### Data that must stay real

- User Insights: `clicks`, `uniqueVisitors`, `trackedEvents`, `daily`, `topReferrers`, `topCountries`, `topBrowsers`, `topPlatforms`, `eventsByName`.
- QR insights: `totalScans`, `daily`, `countryCount`, `browserCount`, `deviceCount`, `topReferrers`, `topCountries`, `topBrowsers`, `topPlatforms`, `topDevices`. QR counts come from anonymous scan rows, not click or consent-based event records.
- Admin insights: `accounts`, `workspaces`, `subscriptions`, `engagement`, `daily`, `resources`, `dimensions`.
- Admin users: `overview`, paginator, filters, roles, plans.
- Do not add a fake "revenue" KPI to the user area. Revenue belongs to admin billing and only where provider data exists.

---

## 11. Implementation map

| Concern                            | File                                                                                                                                                                                         |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tokens, base type, table utilities | `resources/css/app.css`                                                                                                                                                                      |
| Buttons                            | `resources/js/components/ui/button.tsx`                                                                                                                                                      |
| Cards                              | `resources/js/components/ui/card.tsx`                                                                                                                                                        |
| Badges/chips                       | `resources/js/components/ui/badge.tsx`                                                                                                                                                       |
| Inputs/selects                     | `resources/js/components/ui/input.tsx`, `select.tsx`                                                                                                                                         |
| Shell                              | `resources/js/components/app-sidebar.tsx`, `app-sidebar-header.tsx`, `app-shell.tsx`, `components/ui/sidebar.tsx`                                                                            |
| Admin shell                        | `resources/js/components/admin/admin-sidebar.tsx`, `layouts/admin-layout.tsx`                                                                                                                |
| KPI cards                          | `resources/js/components/dashboard/stat-card.tsx`                                                                                                                                            |
| Segmented control                  | `resources/js/components/ui/segmented-control.tsx`                                                                                                                                           |
| User dashboard                     | `resources/js/pages/analytics/index.tsx`, `components/analytics/analytics-dashboard.tsx`                                                                                                     |
| Traffic-source charts              | `resources/js/components/analytics/traffic-sources.tsx`, `ranked-breakdown-card.tsx`                                                                                                         |
| Analytics window and line chart    | `resources/js/components/analytics/window-picker.tsx`, `daily-line-chart.tsx`                                                                                                                |
| Country flags                      | `resources/js/components/analytics/country-flag.tsx` + local `flag-icons` assets                                                                                                             |
| QR scan insights                   | `resources/js/components/qr-codes/qr-scan-insights.tsx`, `qr-code-status.ts`, `app/Services/QrCodes/QrCodeAnalytics.php`, `app/Services/Analytics/RequestDimensions.php`                     |
| Shared form dialog chrome          | `resources/js/components/forms/settings-panel-dialog.tsx`, `form-error-summary.tsx`                                                                                                          |
| QR creation modal                  | `resources/js/components/qr-codes/qr-code-form-dialog.tsx`, `qr-code-settings-dialogs.tsx`, `qr-code-form-data.ts`                                                                           |
| Admin dashboard                    | `resources/js/pages/admin/insights/index.tsx`; `components/admin/insights/` activity, account-health, resource-totals, dimension, donut, and range-picker components                         |
| Admin insights data                | `app/Http/Controllers/Admin/AdminInsightsController.php`, `app/Http/Requests/Admin/AdminInsightsRequest.php`, `app/Services/Admin/AdminInsights.php`, `resources/js/types/admin-insights.ts` |
| List pages                         | `resources/js/pages/links/index.tsx`, `pages/admin/users/index.tsx`, shared `resource-list/*`                                                                                                |
| Page headers                       | `resources/js/components/page-header.tsx`                                                                                                                                                    |

---

## 12. Acceptance checklist

A screen is done when all are true:

- [ ] Sidebar is 240 px, flat `#f5f5f5`, 1 px border, 40 px nav rows, gray active pill, and pinned identity below the footer actions; help and logout are in the profile menu.
- [ ] Top bar is 64 px with a 32 px trigger, a workspace/administration context label, and circular search/notification buttons; the page's primary action sits in its page header.
- [ ] Page title is 24/600 with no eyebrow or decorative icon; description is 14 px muted.
- [ ] KPI cards are white, 16 px radius, 16 px padding, 24/600 values, 13/500 muted labels, and a soft-accent metric icon; no fake deltas.
- [ ] Chart panels are white, 16 px radius, 16/600 titles, 11 px muted axes, no vertical gridlines, accent-ramp series.
- [ ] Every insights page follows §7.2c: one main time series, a full-width country map, donut distributions, ranked lists for referrers/pages, one threshold note, and no exact daily-values table on aggregate pages.
- [ ] Tables have a `#efeff0` header, 56 px white rows, `#e4e4e7` separators, avatar identity cells, right-aligned numeric/actions.
- [ ] Buttons are 8 px radius; inputs/selects 12 px; chips are 20 px pills; cards 16 px.
- [ ] No second accent family, gradient background, glow, oversized shadow, emoji icon, or all-caps label.
- [ ] Keyboard focus is visible on every control; icon-only buttons have accessible names.
- [ ] 390 px viewport: no page-level horizontal scroll; sidebar is a sheet; KPI grid and charts stack; table becomes cards or scrolls inside its panel.
- [ ] Both light and dark themes use the semantic tokens above; dark is not a tinted blue-gray inversion.
