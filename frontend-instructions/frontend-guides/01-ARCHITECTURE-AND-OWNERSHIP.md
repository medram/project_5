# 01 — Architecture and ownership

Read this when you add a page, component, hook, or type, or when you are
deciding where code belongs.

## Directory map

| Path | Owns |
|---|---|
| `resources/js/pages/**` | One file per Inertia page; page composition, `<Head>`, page header |
| `resources/js/layouts/**` | Shells (`UserLayout`, `AdminLayout`, `AuthLayout`, `SettingsLayout`) |
| `resources/js/components/**` | Domain components grouped by feature |
| `resources/js/components/ui/**` | shadcn-derived primitives, locally themed |
| `resources/js/hooks/**` | Reusable browser behavior |
| `resources/js/lib/**` | Pure utilities |
| `resources/js/types/**` | TypeScript contracts |
| `resources/js/routes/**` | Generated Wayfinder route helpers |
| `resources/js/actions/**` | Generated Wayfinder controller actions |
| `resources/js/wayfinder/**` | Generated internals |
| `resources/css/app.css` | Tailwind entry, CSS variables, base layers |

## Ownership

- A **page** owns routing composition, the document head, the page header, and
  the arrangement of domain components. It does not own reusable UI.
- A **domain component** owns one reusable UI slice for a feature (`LinkFormDialog`,
  `QrScanInsights`, `AccountHealthCard`). It may fetch nothing; props carry data.
- `components/ui` owns primitives: button, dialog, field, table shell styling,
  chart container. No business logic.
- `hooks` owns browser behavior: current URL, clipboard, appearance, mobile,
  translations. Not data fetching.
- `lib` owns pure functions and constants. No React, no Inertia.
- `types` owns contracts shared by pages and components. Page-local prop types
  may stay in the page.
- `routes` and `actions` are generated. Never edit them.

## Dependency direction

```text
pages → domain components → components/ui + lib
pages → hooks → lib
pages/components → types
routes/actions → wayfinder (generated)
```

- Components must not import pages.
- `components/ui` must not import domain components.
- `lib` must not import React or Inertia.
- Generated code is imported for its public helpers only; never patched.
- A page may import another page only for a shared static type. Prefer moving
  the type to `types/`.

## Feature organization

Use a domain folder when a component serves one product area:

`components/links`, `components/qr-codes`, `components/bio-pages`,
`components/link-pages`, `components/analytics`, `components/admin/insights`,
`components/resource-list`.

Promote a component to a shared folder only after a second consumer exists.
Do not create barrel files; import the exact module. Do not create a generic
`components/common` dumping ground.

## Layout registry

`resources/js/app.tsx` maps page names to layouts with one switch. A page’s
static `.layout` object supplies `breadcrumbs` or auth `title`/`description`.
`resources/js/pages/links/revisions.tsx` replaces the layout;
`resources/js/pages/auth/two-factor-challenge.tsx` uses `setLayoutProps()` at
runtime. Settings pages receive `[UserLayout, SettingsLayout]`. Public pages
return `null` and render their own
`<main>`. Do not add a second registry or wrap a page in a shell manually.

## App providers and global UI

`createInertiaApp({ withApp })` mounts `TooltipProvider` and `Toaster` around
the app. `initializeTheme()` runs once at startup; the Inertia progress color is
configured in `app.tsx`. Pages must not mount another `Toaster`,
`TooltipProvider`, or theme initializer.

## Hooks

Hooks are for reusable browser behavior only:

- `useCurrentUrl` — `isCurrentUrl`, `isCurrentOrParentUrl`, `whenCurrentUrl`.
- `useClipboard` — `[copiedText, copy]`.
- `useAppearance` — theme state and `initializeTheme`.
- `useTranslation` — shared dictionary lookup.
- `useIsMobile` — viewport detection for shell behavior.
- `useInitials` — avatar fallback.
- `useFlashToast` — server flash to Sonner (mounted by `Toaster`).
- `useTwoFactorAuth` — 2FA setup state.

There are no data-fetching hooks. Page props are the data layer. Do not create
`useLinks`, `useUsers`, or a `useApi` wrapper.

## Naming and files

- Files: kebab-case (`link-form-dialog.tsx`, `use-current-url.ts`).
- Components: PascalCase named exports; pages use a default export.
- Hooks/functions: camelCase.
- `.tsx` only when the file returns JSX; otherwise `.ts`.
- Import with the `@/` alias, never long relative chains.
- One component per file unless two are inseparable and private.

## State ownership

| State | Owner | Example |
|---|---|---|
| Server data | Inertia page props | Rows, totals, permissions |
| Filters and range | URL query | `?q=…&days=30` |
| Selection, open/closed, drafts | Local `useState` | `selectedIds`, dialog open |
| Form values | `useForm` or `<Form>` | Dialog and page forms |
| Global UI | Providers in `app.tsx` | Tooltips, toasts, theme |

There is no global store. Do not add one for a single surface.

## Where to add code

| Task | Location |
|---|---|
| New page | `pages/<area>/<name>.tsx` + controller + route + layout registry check |
| New admin page | `pages/admin/<area>/` + `AdminLayout` via `admin/` prefix |
| New list | Page + `components/resource-list/*` + a row component in the domain folder |
| New form | Page or dialog component in the domain folder; see guide 04 |
| New dialog | Domain component; compose `components/ui/dialog` |
| New chart | Domain component; compose `components/ui/chart` |
| New settings section | `pages/admin/settings/section.tsx` + `settings-form.ts`; no new page shell |
| New shared primitive | `components/ui` only after 2+ consumers and a real API |

## Size and extraction

Split a page when it owns more than one of: a list, an editor, a dialog family,
a filter system. Extract the dialog and the filter toolbar first. Known large
files (do not copy them as templates):

- `pages/links/index.tsx`, `pages/bio-pages/edit.tsx`,
  `pages/link-pages/edit.tsx`, `pages/admin/settings/section.tsx`,
  `pages/admin/users/show.tsx`.

Do not create a generic abstraction for one consumer. A second real consumer
is the threshold.

## Known debt

- Dead layouts/components remain: `layouts/app/app-header-layout.tsx`,
  `layouts/app/app-sidebar-layout.tsx`,
  `layouts/auth/auth-card-layout.tsx`, `layouts/auth/auth-split-layout.tsx`,
  `components/app-header.tsx`, `resource-list/sort-menu.tsx`.
  Do not copy them; migrate when touched.
- `pages/dashboard.tsx` is a leftover placeholder, not a template.
- Several page files exceed 600 lines. Discover the current set with
  `find resources/js/pages -name '*.tsx' -print0 | xargs -0 wc -l | awk '$1 > 600'`.
  Extract only the slice you are changing.
- `links/index.tsx` uses a `<ul>` grid as a faux table; keep it consistent
  until a real conversion is scoped.

## Checklist

- [ ] The file lives in the owning directory, not a convenience folder.
- [ ] Dependencies point downward: page → component → ui/lib.
- [ ] No data fetching, global store, or barrel file was added.
- [ ] The layout comes from `app.tsx`, not a manual wrapper.
- [ ] Generated files were not edited.
- [ ] A new shared primitive has a second consumer.
