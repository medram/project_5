# 03 — Data, routes, and permissions

Read this when you wire a page to a controller, add a Wayfinder call, filter a
list, mutate data, or gate UI.

## Request lifecycle

```text
Route → middleware/gate → controller → Inertia::render(page, props) → page
```

- Shared props come from `HandleInertiaRequests` on every response.
- Pages read data from props; they do not fetch on mount.
- Inertia mutations normally end in a redirect to an Inertia page. A non-Inertia
  JSON response is not an `onSuccess` page response; use a separately specified
  non-Inertia client when an endpoint actually returns JSON.
- Validation failures redirect back with session errors shared in the Inertia
  `errors` prop; Inertia calls `onError` and preserves form values.

## Page prop contract

- Define the payload in `resources/js/types/*`; the page imports it.
- Paginated data uses `PaginatedCollection<T>` and `PaginationLink` from
  `@/types/pagination`. Laravel sends snake_case pagination keys.
- Echo the active filters back to the page. Do not derive a total on the
  client when the server already returns it.
- Dates are ISO strings; format at render time. Money uses `formatMoney`.
- Keep domain types camelCase inside the payload; map snake_case at the
  controller boundary when the raw model shape would leak.

## Shared props

`auth`, `platform`, `locale`, `translations`, `notificationUnreadCount`,
`notificationPreview`, `workspace`, `sidebarOpen`, `googleAuthEnabled`,
`errors`, `name`. They are globally typed in `resources/js/types/global.d.ts`.

```tsx
import { usePage } from '@inertiajs/react';

const { auth, workspace, errors } = usePage().props;
```

Use `usePage<T>()` only when a page deliberately narrows a prop that is not in
the shared type. After the shared-type pass, `workspace`, `googleAuthEnabled`,
and `errors` are global; do not add redundant generics for them.

`auth.adminAbilities` is the admin UI capability list. Page props may also
carry `can*` booleans. Both are presentation hints only.

## Wayfinder

```tsx
import { index as linksIndex, store as storeLink } from '@/routes/links';
import files from '@/routes/admin/files';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

<Link href={linksIndex()}>Short links</Link>;

router.get(files.index().url, { q }, { preserveScroll: true });

<Form {...ProfileController.update.form()}>…</Form>;
```

- Named imports for a few helpers; namespace/default imports for a group.
- `RouteDefinition` works in `<Link>` and breadcrumbs. Use `.url` for
  `router.*` and plain `<a>`.
- `.form()` exists on controller actions and route helpers when form variants
  are generated. Use it for `<Form>`.
- No Ziggy, no global `route()`, no hard-coded product URLs in new code.
  Server-provided pagination and export URLs are the exception.

## Mutations

| Need                                      | API                                                      |
| ----------------------------------------- | -------------------------------------------------------- |
| Page/auth/settings form                   | `<Form {...Action.form()}>`                              |
| Dialog/editor with controlled data        | `useForm<T>()`                                           |
| One-shot action (delete, restore, rotate) | `router.post/patch/delete`                               |
| Filter or range change                    | `router.get`                                             |
| Update via multipart                      | `useForm` with `forceFormData` or a `FormData` transform |

Never use `router.put`; Laravel routes declare `put` where needed, but the UI
uses `patch` or a `_method` transform. Never submit with a raw `fetch`.

## Visit options

```tsx
router.post(url, data, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ['rows', 'filters'],
});
```

- `preserveScroll: true` is the default for mutations. Omit it only when the
  destination is intentionally a new page top.
- `preserveState: true` for filters so local input state survives.
- `replace: true` for range/window changes so the back button is not spammed.
- `only` for partial reloads when a page has expensive unrelated props.
- `onSuccess` closes/resets a dialog; `onError` focuses the summary or field.

## Filters and URL state

Canonical admin pattern:

```tsx
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
```

- Sync local state from `filters` when the server response changes.
- Use `key={listQuery}` on a filter form when a native GET form must reset.
- Two pages use a native GET `<form>` (`links`, `folders`); new code SHOULD use
  the `router.get` pattern unless a no-JS fallback is required.
- Do not debounce by default. Add it only when the surface is a live search and
  the interaction is specified.
- Reset returns to the base URL and clears local state.

## Permissions

- Authorization is server-side: middleware (`platform:*`), `Gate`, policies.
- UI hiding is not authorization. A hidden button is still a vulnerability if
  the route is open.
- Admin nav and pages use `auth.adminAbilities` (e.g.
  `admin.files.manage`) and page `can*` props.
- Disable a control when the user can see the surface but cannot act, and
  explain why with a tooltip or description.
- Never send an admin-only prop to a non-admin page “just in case”.

## Partial reloads and polling

Only analytics uses a 30-second reload (`router.reload({ only: [...] })`) and
it is visibility-guarded. Do not add `usePoll` or background polling without a
visibility check and a specified interval. A manual refresh button is preferred
for admin surfaces.

## Serialization

- Dates: ISO string → `Intl.DateTimeFormat` at render.
- Money: `formatMoney(amount, currency)`.
- Countries: `countryName(code)` and `CountryFlag`.
- Numbers: `toLocaleString()`; use `tabular-nums` in tables and KPIs.
- Do not format in two places for the same unit. If a helper does not exist and
  the format repeats, add it to `lib` and test it.

## Known gaps

- A few raw product URL literals remain in older pages (`/dashboard/...`).
  New code uses Wayfinder.
- Filter conventions are duplicated: admin pages use `useState + router.get`;
  `links`/`folders` use a native GET form. Follow the pattern of the page you
  are editing and document a conversion if you change it.
- `workspace`, `googleAuthEnabled`, and `errors` are now global; keep local
  generics only where a page intentionally narrows a different prop.
- No page-level Inertia error page exists; failures render the framework
  fallback. Do not invent a client error boundary as a substitute.

## Checklist

- [ ] Props typed from `types/*`, pagination via `PaginatedCollection`.
- [ ] Wayfinder helpers used; no raw product URL.
- [ ] Mutation API matches the surface (Form/useForm/router).
- [ ] `preserveScroll` set; filters preserve state.
- [ ] Server authorization exists; UI gate is additive.
- [ ] Dates/money/countries use shared formatting.
