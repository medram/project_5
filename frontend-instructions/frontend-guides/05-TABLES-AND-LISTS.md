# 05 — Tables and lists

Read this when you add or change a list, table, filter, pagination block, bulk
action, or empty state.

## List page anatomy

1. `PageHeader` with the primary create action.
2. Optional KPI strip (one number per card; no charts inside).
3. Toolbar: search, filters, view toggle.
4. Active filter chips.
5. Bulk tray when rows are selected.
6. Table on desktop, cards below the page’s breakpoint (`lg` for admin lists,
   `md` for user lists).
7. Pagination below the surface.
8. Empty state inside the surface when there are zero rows.

Spacing follows the page wrapper (`gap-5`). Do not add a second section title
above the toolbar.

## Choose the representation

| Case                     | Representation                                                       |
| ------------------------ | -------------------------------------------------------------------- |
| Dense admin records      | Native `<table>` inside `.lp-table-shell` at `lg+`, cards below `lg` |
| User-facing short links  | Semantic `<ul>` reflowing grid (current `links/index.tsx`)           |
| Few fields, mobile-first | Card list only                                                       |
| Wide audit rows          | Table with internal horizontal scroll; never page-level scroll       |

Page-level horizontal scroll at 390 px is a failure. A scroll container inside
the surface is acceptable when a card variant is impossible.

## Canonical table

```tsx
<div className="lp-table-shell">
    <table data-table-surface className="w-full text-sm" aria-label="Users">
        <thead>
            <tr>
                <th scope="col">Account</th>
                <th scope="col" className="text-right">
                    Links
                </th>
            </tr>
        </thead>
        <tbody>
            {rows.map((row) => (
                <tr key={row.id}>
                    <td className="p-3 font-medium">{row.name}</td>
                    <td className="p-3 text-right tabular-nums">{row.links}</td>
                </tr>
            ))}
        </tbody>
    </table>
</div>
```

- `.lp-table-shell` provides the rounded border and overflow clipping.
- `data-table-surface` provides header, row, hover, and selected styling.
- Header cells use `scope="col"`; numeric cells use `tabular-nums`.
- Row height is about 56 px on desktop. Do not compress below 44 px on touch.
- Sortable headers set `aria-sort` on the `<th>` and label the control.
- The actions column is last and right-aligned; icon buttons need names.

## Shared primitives

| Component                 | Props                                               | Use                                                                 |
| ------------------------- | --------------------------------------------------- | ------------------------------------------------------------------- |
| `ResourceListToolbar`     | `action`, `className`, `children`                   | Native GET form wrapper; used by `links`/`folders`, not admin lists |
| `ResourceListFilterChips` | `filters: { key, label, value, href }[]`            | Active filter removal                                               |
| `ResourceListViewToggle`  | view state + labels                                 | Table/card switch when both are offered                             |
| `ResourceListBulkTray`    | selection count + actions                           | Bulk actions after selection                                        |
| `ResourceListPagination`  | `links`, `total`, `ariaLabel`, `singular`, `plural` | User-list pagination                                                |

Admin lists compose their own toolbar with `router.get` and a richer footer;
they do not use `ResourceListToolbar` or `ResourceListPagination`. Use the
shared primitives for user lists and only when the interaction exists.

`resource-list/sort-menu.tsx` exists but has no consumer. Do not add it to a
page without a real sort interaction.

## Filters

Canonical admin filter:

```tsx
const [query, setQuery] = useState(filters.q ?? '');

function apply(event: FormEvent) {
    event.preventDefault();
    router.get(
        path,
        {
            q: query.trim() || undefined,
            state: state === 'any' ? undefined : state,
        },
        { preserveScroll: true, preserveState: true },
    );
}
```

- Filters live in the URL. The controller echoes them back.
- Reset clears local state and calls `router.get(path, {})`.
- Native GET forms are allowed for simple user lists (`links`, `folders`) where
  no-JS submission is useful. Keep `key={listQuery}` on the form.
- Hidden inputs preserve unrelated query parameters.
- Show active chips when a filter is non-default.
- Search is debounced only when the interaction is a live search; otherwise
  submit on Enter or the Apply button.
- A filter change updates the URL and preserves the scroll position.

## Pagination

- User lists: `ResourceListPagination` with a translated `ariaLabel` and a
  total/denominator label.
- Admin lists: the richer footer with “from–to of total” plus Laravel links.
  Give each `<nav>` an `aria-label` (`Users pagination`, `Files pagination`, …).
- Preserve scroll on page change.
- Do not hide the total. “12 of 48” is more useful than a bare page number.
- Do not fetch pages client-side; follow the server links.

## Selection and bulk actions

- Keep `selectedIds` in local state; prune when the page data changes.
- Select-all applies to the current page unless the UI explicitly says
  “all matching”.
- Show the bulk tray only when at least one row is selected.
- Destructive bulk actions require confirmation and a count.
- Use `data-selected={selected || undefined}` for visual selection; do not
  misuse `aria-selected` on `<li>` or `<tr>` as a CSS hook.
- Native checkboxes or the `Checkbox` primitive both need an accessible name
  (`Select folder {name}`).

## Row actions

- Prefer one primary link plus a dropdown for secondary actions.
- Icon-only buttons need `aria-label` and a tooltip when the icon is ambiguous.
- Destructive items use the destructive variant and a confirmation dialog.
- Do not hide an action that the user cannot perform without explaining why;
  disable it with a reason.
- Row click may navigate; checkbox and action clicks must stop propagation.

## Empty and loading

```tsx
<Empty>
    <EmptyHeader>
        <EmptyMedia variant="icon">
            <Link2 aria-hidden="true" />
        </EmptyMedia>
        <EmptyTitle>No short links yet</EmptyTitle>
        <EmptyDescription>
            Create your first route to start tracking clicks.
        </EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
        <Button>Create short link</Button>
    </EmptyContent>
</Empty>
```

- One sentence and one action. Explain the next step, not the absence.
- Filtered-empty copy differs from truly-empty copy (“No links match these
  filters” with a clear-filters action).
- No skeleton screens. Inertia’s progress bar covers navigation; keep the
  previous surface visible during a partial reload.
- No demo rows. An empty state is the honest state.

## Known debt

- `links/index.tsx` is a `<ul>` faux table, not a native table. Keep it
  consistent until a conversion is scoped.
- Admin lists (`admin/users`, `admin/files`) still use plain messages for zero
  rows instead of `Empty`. New code uses `Empty`; migrate when touched.
- `admin/users` broadcast dialog hand-rolls `<p className="text-destructive">`
  instead of `FieldError`. Do not copy it.
- Admin pagination is duplicated across several pages instead of using the shared
  component. Copy the footer pattern, not a new one.
- `links` and `folders` use native GET forms; admin pages use `router.get`.
- Many lists have no filters. Adding a filter is a feature, not a consistency
  fix; scope it separately.
- `aria-selected` still appears on the bio editor’s tabs, where it is valid.
  Do not remove it.

## Filter toolbar recipe

```tsx
// source: resources/js/pages/admin/content/index.tsx
const [query, setQuery] = useState(filters.q ?? '');

function apply(event: FormEvent) {
    event.preventDefault();
    router.get(
        path,
        { q: query.trim() || undefined },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function reset() {
    setQuery('');
    router.get(path, {}, { preserveScroll: true });
}

<form
    onSubmit={apply}
    className="lp-panel flex flex-wrap items-center gap-2 p-3"
>
    <Input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        aria-label="Search"
        className="w-full sm:max-w-sm"
    />
    <Button type="submit">Apply</Button>
    <Button type="button" variant="ghost" onClick={reset}>
        Reset
    </Button>
</form>;
```

Show active chips below the toolbar; each chip removes its own filter.

## Checklist

- [ ] Page header, toolbar, chips, surface, pagination, empty state in order.
- [ ] Table has an accessible name; headers use `scope`.
- [ ] Numeric columns use `tabular-nums`.
- [ ] Filters are URL state; reset works; chips remove.
- [ ] Pagination has a label and a total.
- [ ] Selection uses `data-selected`, not `aria-selected`.
- [ ] No page-level horizontal scroll at 390 px; cards exist below the page
      breakpoint (`lg` admin, `md` user lists).
