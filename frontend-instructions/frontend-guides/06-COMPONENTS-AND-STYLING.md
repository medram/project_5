# 06 — Components and styling

Read this when you choose a component, add a primitive, or style a surface.

## Token map

Exact values live in `docs/DESIGN.md` §3–§5; this table is a usage map. If they
disagree, DESIGN wins.

| Need | Token / utility |
|---|---|
| Page background | `bg-background`, `text-foreground` |
| Card surface | `bg-card`, `text-card-foreground`, `border-border` |
| Secondary surface | `bg-surface-secondary`, `bg-surface-tertiary` |
| Divider | `border-separator` |
| Accent | `bg-primary`, `text-primary`, `bg-primary-soft` |
| Success | `text-success`, `bg-success-soft`, `text-success-soft-foreground` |
| Warning | `text-warning`, `bg-warning-soft`, `text-warning-soft-foreground` |
| Danger | `text-danger`, `bg-danger-soft`, `text-danger-soft-foreground` |
| Legacy error alias | `text-destructive`, `border-destructive` — shadcn-compatible; `FieldError` still uses it |
| Charts | `var(--chart-1…5)`, `bg-chart-*` |
| Muted text | `text-muted-foreground` |

Use the token, never the hex. Never hard-code a hue; the platform accent remaps
`--primary` and the chart ramp at runtime. `destructive` is the
shadcn-compatible error token used by `FieldError` and form borders; the
semantic status family is `danger*` with soft foregrounds. Do not rewrite
`text-destructive` to `text-danger` inside primitives.

Decorative avatar fallbacks are the exception: follow `docs/DESIGN.md` §7.8
and reuse `resources/js/components/user-avatar.tsx`. Its existing gradient
palette is tracked by the checker; it is not a status-color pattern.

## Type scale

| Role | Classes |
|---|---|
| Page title | `text-2xl leading-8 font-semibold tracking-[-0.02em] text-balance` |
| Section title | `text-xl font-semibold tracking-[-0.02em]` or `text-base leading-6 font-semibold` |
| KPI value | `text-2xl leading-7 font-semibold tracking-[-0.02em] tabular-nums` |
| Body | `text-sm leading-5` |
| Label | `text-sm font-medium` |
| Meta | `text-xs leading-4 font-medium text-muted-foreground` |
| Micro | `text-[11px]` only for chart ticks or a dense badge |

Sentence case everywhere. No uppercase tracked labels. Numbers that align in a
column use `tabular-nums`.

## Shape and spacing

- Card radius 16 px (`rounded-2xl` / `--radius-xl`).
- Control radius 8–12 px (`rounded-lg` / `rounded-xl`); buttons 8 px.
- Chips are pills.
- Nav items 8 px.
- Spacing stays on the 4 px scale: 4/8/12/16/20/24/32/40.
- Page gap is 20 px; card padding is 16 px; section gap inside a card is 16 px.

## shadcn/ui rules

- Use the existing primitives in `components/ui`. Do not write a parallel
  button, dialog, select, field, or card.
- Add a missing primitive with `npx shadcn@latest add <name>`, then apply only
  the local token/radius theme. Do not regenerate `button.tsx`, `card.tsx`, or
  `badge.tsx` to upstream defaults; the local theme is authoritative.
- `components/ui` is excluded from lint/format. Do not reformat it as part of
  another change.
- Compose with `cn`; express variants with `cva` when a component has more than
  two visual modes.
- Use `asChild` when a primitive should render a link or a custom trigger.
- Forward refs where a parent needs the DOM node (`PasswordInput`, form fields).
- No business logic, route calls, or page props inside `components/ui`.

## Component API rules

- Composition over boolean modes: pass `children` or slots instead of adding
  `variant="withHeader"`.
- Always accept `className` and merge it last with `cn`.
- Prefer `React.ComponentProps<'button'>` over re-declaring DOM props.
- Keep `data-slot` attributes; styling and tests rely on them.
- A component that fetches data belongs in the page, not in `ui`.

## Cards and sections

- `Card`, `CardHeader`, `CardContent`, `CardTitle`, `CardDescription` are the
  canonical section shell.
- `.lp-panel` and `.lp-table-shell` are CSS utilities for panel and table
  shells. Do not add a third shell.
- Do not write `rounded-2xl border bg-card` in new code; use `Card` or
  `.lp-panel`.
- A section heading inside a card is an `h2`/`h3` with the section type scale,
  not a page title. Use `Heading` for a standalone section heading.

## Color

- One accent per surface. Do not rainbow a single series or a group of KPI
  cards.
- Status uses semantic tokens only: success, warning, danger, muted. No
  `text-green-600`, `text-red-500`, `bg-blue-50`.
- On a tinted background use the matching `*-soft-foreground`; do not pair a
  soft background with plain `text-foreground`.
- Destructive `Alert` uses `border-danger/30 bg-danger-soft
  text-danger-soft-foreground`.
- No gradient text, glow, or colored shadow.
- Charts use one accent and the chart ramp; do not assign arbitrary colors.
- A request to unslop, polish, or fix "AI-looking" UI reads the `unslop` skill
  after this guide. Its direction step is visual judgment, not a license to
  change product copy, features, or section order.

## Icons

- Lucide only. Size `size-4` inside buttons; `size-5` for standalone status.
- Use an icon when it speeds recognition (create, delete, external link), not
  as decoration. Do not put an icon on every label or every KPI.
- Decorative icons get `aria-hidden="true"`; icon-only buttons get
  `aria-label`.
- Use `data-icon="inline-start"` for the spacing contract next to a label.
- Do not use an icon as the only carrier of status. Pair it with text.

## Charts

```tsx
<ChartContainer config={config} className="h-[300px] w-full">
    <LineChart data={data} accessibilityLayer>
        <CartesianGrid vertical={false} />
        <XAxis dataKey="date" tickLine={false} axisLine={false} />
        <YAxis tickLine={false} axisLine={false} width={36} />
        <ChartTooltip content={<ChartTooltipContent />} />
        <Line
            dataKey="clicks"
            stroke="var(--color-clicks)"
            strokeWidth={2}
            dot={false}
            isAnimationActive={false}
        />
    </LineChart>
</ChartContainer>
```

- `config` colors come from `var(--chart-*)`.
- `isAnimationActive={false}` everywhere.
- `ChartContainer` renders `role="img"`; pass an `aria-label` that states the
  metric, range, and total.
- One main series at a time; use a segmented control for mutually exclusive
  metrics.
- Empty data renders the `Empty` block, not an empty axis.
- No second chart library. No country bar chart.

## Responsive

| Breakpoint | Behavior |
|---|---|
| 390 | Single column, cards instead of tables, bottom nav visible |
| 640 (`sm`) | Two-up donuts, header actions inline |
| 768 (`md`) | Two-column layouts; user lists may switch to tables |
| 1024 (`lg`) | Persistent sidebar; admin cards switch to tables; settings rail vertical |
| 1280 (`xl`) | Wider grids |

- Put `min-w-0` on flex/grid children that contain text or tables.
- Use `overflow-x-clip` on the shell content; never `overflow-x-hidden` on a
  page that needs sticky positioning.
- Bound popovers to the viewport; use `max-h` and `overflow-y-auto`.
- Mobile dialogs become bottom sheets only where the primitive supports it;
  otherwise use a full-height dialog with a fixed footer.

## Trusted HTML

- Never pass user content to `dangerouslySetInnerHTML`. The only allowed
  user-content site is `resources/js/pages/content/show.tsx`, which renders a
  server-sanitized body; the 2FA QR SVG in
  `resources/js/components/two-factor-setup-modal.tsx` is generated content. A
  new HTML surface needs a server sanitizer and a documented trust boundary
  before it ships.
- Do not re-sanitize sanitized server HTML in the client, and do not build HTML
  strings in a page.

## Anti-patterns

| Anti-pattern | Real example | Replacement |
|---|---|---|
| Raw palette for status | `text-green-600` | `text-success-soft-foreground` |
| Hard-coded accent | `bg-[#0a6fd0]` | `bg-primary` |
| Parallel primitive | New `components/button.tsx` | `components/ui/button` |
| Ad-hoc card shell | `rounded-2xl border bg-card` | `Card` / `.lp-panel` |
| Page title in a card | `<h1>` inside `CardHeader` | `PageHeader` |
| Second chart library | ApexCharts | Recharts + `ChartContainer` |
| Emoji status | “✅ Active” | Badge/dot + text |
| Uppercase tracked label | `uppercase tracking-widest` | Sentence case |
| Shadow glow | Colored `box-shadow` | Border + surface token |

## Checklist

- [ ] Tokens only; no raw hex or palette class.
- [ ] Existing primitive reused; `className` merged with `cn`.
- [ ] One accent; status uses semantic tokens with soft foreground.
- [ ] Icons labelled or hidden; icon-only control has a name.
- [ ] Chart uses `ChartContainer`, chart ramp, no animation, `role="img"`.
- [ ] Responsive behavior verified at 390/768/1024/1440.
