# 02 — Pages, layouts, and navigation

Read this when you create or modify a page, page header, breadcrumb, or any
navigation element.

## Layout assignment

`resources/js/app.tsx` owns the mapping. Current behavior:

| Page prefix                                                                                                            | Layout                         |
| ---------------------------------------------------------------------------------------------------------------------- | ------------------------------ |
| `welcome`, `bio-pages/public`, `link-pages/public`, `links/overlay`, `links/unlock`, `qr-codes/unlock`, `content/show` | `null`                         |
| `auth/`                                                                                                                | `AuthLayout`                   |
| `admin/`                                                                                                               | `AdminLayout`                  |
| `settings/`, `webhooks/`, `billing/`                                                                                   | `[UserLayout, SettingsLayout]` |
| Everything else                                                                                                        | `UserLayout`                   |

A page supplies `breadcrumbs` (product pages) or `title`/`description` (auth
pages) through its static `.layout` object. `setLayoutProps()` is used at
runtime by `resources/js/pages/auth/two-factor-challenge.tsx`.
`resources/js/pages/links/revisions.tsx` replaces the layout for a full-screen
diff. Do not add a second layout switch.

## Shell anatomy

- `UserLayout`: sidebar, sticky top bar, page content, mobile bottom nav.
- `AdminLayout`: admin sidebar, sticky top bar with platform search, content.
- `SettingsLayout`: horizontal rail of account sections at small widths, a
  208 px rail at `lg`, then a narrow content column (or wide for billing, API
  keys, webhooks, workspaces).
- The skip link and `<main id="main-content">` live in the shell, not in each
  page. Public pages with `null` layout must render their own `<main>`.
- Mobile bottom nav is 56 px plus safe-area padding; user content reserves
  space for it in `UserLayout`.

## Page anatomy

Required order:

1. `<Head title="…" />`.
2. Max-width wrapper.
3. `PageHeader` (product pages).
4. Optional KPI row.
5. Optional toolbar.
6. Main content.
7. Pagination below the surface.
8. Optional closing note/actions.

Auth pages omit steps 3–8; their title comes from `AuthLayout`. Public pages
own their composition and are not required to use `PageHeader`.

## Page wrapper

The canonical wrapper:

```tsx
<div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
```

Use it verbatim. Exceptions:

- Settings pages sit inside `SettingsLayout` and use `space-y-6` inside its
  narrow column; do not add the max-width wrapper again.
- Narrow focused pages (restore, checkout result) use `max-w-xl` and their own
  padding.
- Public pages use their own shell.
- A page that already renders `<main>` uses the same classes on `<main>` when
  it owns the landmark.

Do not introduce a `PageContainer` component. The wrapper is a class contract,
not an abstraction.

## `PageHeader`

```tsx
import { PageHeader } from '@/components/page-header';

<PageHeader
    title="Short links"
    description="Create and monitor every route in this workspace."
    actions={<Button>Create short link</Button>}
/>;
```

Rules:

- One `h1` per page. `PageHeader` renders it.
- Title is 24/600 with tight tracking; do not add typography classes.
- One description sentence, max-width constrained.
- Actions align right on `sm+`, wrap on mobile, and are optional.
- No eyebrow, decorative icon, badge row, or duplicate title above it.
- Back links stay in a separate ghost-button row above `PageHeader`; never put
  them in `actions`.
- Detail/identity headers are allowed only when a page has an avatar, badge,
  or back-link identity block: `admin/users/show`, `link-pages/edit`,
  `bio-pages/edit`. Reuse the same `h1` typography and keep the back link
  outside the action cluster.

## Auth pages

`AuthLayout` renders the title and description from the page’s `.layout`
object. Do not add `PageHeader` or an `<h1>` to an auth page. The form is the
page.

## Navigation

- Internal navigation uses `<Link>` for links and `router.visit` for
  programmatic visits. Never `window.location` for an internal route.
- Use Wayfinder helpers from `@/routes/*`; pass `RouteDefinition` directly to
  `<Link>` or breadcrumbs, and `.url` to `router.*` and plain `<a>`.
- Current page links use `aria-current="page"`. Parent matches use
  `aria-current="location"`. `nav-main`, the settings rail, and the admin
  sidebar already implement this.
- Prefetch list and nav links where the destination is predictable. Do not
  prefetch destructive or parameter-heavy links.
- Mobile nav closes on navigation via `useSidebar`/`useMobileNavigation`.

## Breadcrumbs

`breadcrumbs` is an array of `{ title, href }` on the page’s `.layout`. Nested
routes include the parent and the current page. Admin pages omit breadcrumbs;
the admin shell communicates location through the sidebar. Public pages do not
use breadcrumbs.

## New page checklist

- [ ] Controller returns `Inertia::render('area/page', props)`.
- [ ] Route uses the product prefix and middleware/gate.
- [ ] Layout is handled by the `app.tsx` switch; no manual shell.
- [ ] `<Head title>` is present and specific.
- [ ] Wrapper uses the canonical class string.
- [ ] `PageHeader` used, or the page is an allowed exception.
- [ ] Empty state exists for zero rows.
- [ ] 390 px has no page-level horizontal scroll.
- [ ] Keyboard focus order and one `h1` verified.

## Known variants

- Raw `<h1>` remains only on: `account/restore`, `admin/users/show`,
  `content/show`, `link-pages/edit`, `links/overlay`, `links/splash`,
  `pricing/index`, `welcome`, and the `sr-only` title in
  `qr-codes/insights`. Do not add another.
- The old `Heading variant="page"` and `variant="small"` APIs are gone.
  `Heading` is a section heading (`h2`) only.
- `pages/dashboard.tsx` is a placeholder. Do not use it as a template.
- `links/insights.tsx` and older analytics pages keep bespoke compositions;
  new insights pages follow `docs/DESIGN.md` §7.2c.

## Page recipe

```tsx
// source: resources/js/pages/folders/index.tsx (structure)
import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { FolderIndexProps } from '@/types/folders';
import { index as foldersIndex } from '@/routes/folders';

export default function Folders({ folders }: FolderIndexProps) {
    return (
        <>
            <Head title="Folders" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Folders"
                    description="Group related links into collections."
                    actions={<Button>Create folder</Button>}
                />
                {/* Render folders.data here. */}
            </div>
        </>
    );
}

Folders.layout = {
    breadcrumbs: [{ title: 'Folders', href: foldersIndex() }],
};
```

## KPI row recipe

```tsx
// source: resources/js/pages/admin/insights/index.tsx (KPI pattern)
import { Building2, UsersRound } from 'lucide-react';

<section
    aria-label="Platform totals"
    className="grid grid-cols-2 gap-3 lg:grid-cols-4"
>
    <StatCard
        icon={UsersRound}
        label="Accounts"
        value={data.accounts.total.toLocaleString()}
        note={`${data.accounts.new} new in window`}
    />
    <StatCard
        icon={Building2}
        label="Workspaces"
        value={data.workspaces.total.toLocaleString()}
        note={`${data.workspaces.new} new in window`}
    />
</section>;
```

`StatCard` accepts `sparkline`; admin aggregate pages must not pass it.

## Checklist

- [ ] Layout comes from the registry.
- [ ] One `h1`, rendered by `PageHeader` or an allowed exception.
- [ ] Canonical wrapper classes.
- [ ] Back links above the header, actions in the header.
- [ ] Navigation uses Wayfinder and `aria-current`.
- [ ] Verified at 1440/1024/768/390 px.
