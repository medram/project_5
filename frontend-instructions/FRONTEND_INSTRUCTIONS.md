# Frontend instructions

Read this file before changing `resources/js` or `resources/css`. It tells you
which rules apply and which guide owns them.

## Authority and reading order

1. `docs/DESIGN.md` — binding visual specification. Read §2–§9 before changing
   a surface.
2. This file — stack rules, non-negotiables, and the task router.
3. The task guide(s) named in the router.
4. The owning code and its tests.
5. `PROJECT-PROFILE.md` for commands, paths, shared props, and layout wiring.

If code and a guide disagree, follow the guide for new code and report the
deviation in your completion note. Never copy a legacy pattern just because it
exists in the file you opened.

## Approved stack

| Layer         | Approved technology                   | Owns                              | Forbidden alternative                     |
| ------------- | ------------------------------------- | --------------------------------- | ----------------------------------------- |
| Routing       | Laravel routes + Inertia v3           | Page props, redirects, validation | Client router, React Router               |
| Route helpers | Wayfinder (`@/routes/*`)              | URL and method pairs              | Ziggy, global `route()`, raw product URLs |
| UI primitives | shadcn/ui (`components/ui`) + Radix   | Buttons, dialogs, menus, fields   | HeroUI, MUI, Chakra, a parallel kit       |
| Styling       | Tailwind CSS v4 + CSS variables       | Layout, spacing, semantic color   | CSS-in-JS, inline style systems           |
| Icons         | Lucide React                          | All product icons                 | Mixed icon packs                          |
| Toasts        | Sonner via `components/ui/sonner`     | Server flash and client copy      | Second toast library                      |
| Charts        | Recharts via `components/ui/chart`    | Time series and distributions     | A second chart library                    |
| Maps          | `react-svg-worldmap` + local flags    | Country maps                      | Country bar charts, CDN flags             |
| Rich text     | Tiptap                                | Bio/page editors                  | Contenteditable by hand                   |
| Build/tooling | Vite+ (`vp`)                          | Dev, build, lint, tests           | Parallel webpack config                   |
| State         | Inertia page props + local state      | All data                          | React Query, SWR, Redux, Zustand          |
| HTTP          | Inertia `router` / `useForm` / `Form` | Mutations and visits              | Axios, fetch wrappers                     |
| Validation    | Laravel `FormRequest`                 | The only schema                   | Zod, Yup, Formik, RHF                     |

Project: LinkPilot. Stack details and versions live in `PROJECT-PROFILE.md`.

## Task router

Always read `10-AGENT-WORKFLOW.md` before starting and
`09-TESTING-AND-VERIFICATION.md` before claiming completion. Any task that
changes an interactive control, focus behavior, or responsive layout also reads
`08-ACCESSIBILITY-AND-RESPONSIVE.md`. A visual-polish or "make it not look
AI-generated" request also reads the `unslop` skill after the guides.

| Task                                                          | Mandatory guides                      |
| ------------------------------------------------------------- | ------------------------------------- |
| Create or modify a page                                       | 01, 02, 06                            |
| Create an admin page                                          | 02, 03, 05, 06; add 04/07 for dialogs |
| Add a settings section                                        | 02, 04, 07                            |
| Build or change a form                                        | 04, 07                                |
| Add a dialog                                                  | 04, 07                                |
| Add or change a list/table                                    | 03, 05, 06                            |
| Add a chart                                                   | 03, 06, 07                            |
| Change feedback, loading, or empty states                     | 07                                    |
| Touch navigation, focus, or responsive layout                 | 02, 08                                |
| Refactor or move ownership                                    | 01, 10                                |
| Run checks or prove a change                                  | 09                                    |
| Write or update a test                                        | 09                                    |
| Add a dependency or primitive                                 | 06, 10                                |
| Wire a route, controller prop, or permission                  | 03                                    |
| Build a domain surface (links, QR, bio, link pages, insights) | `PROJECT-SURFACES.md` plus 02/05/06   |

## Rule ownership

| Rule domain                                 | Owner                      |
| ------------------------------------------- | -------------------------- |
| Visual tokens, type, composition            | `docs/DESIGN.md`           |
| Stack, forbidden libraries, non-negotiables | `FRONTEND_INSTRUCTIONS.md` |
| Page, layout, navigation                    | 02                         |
| Data, routes, permissions                   | 03                         |
| Forms, validation                           | 04                         |
| Lists, tables                               | 05                         |
| Components, styling                         | 06                         |
| Feedback, overlays                          | 07                         |
| Accessibility, responsive                   | 08                         |
| Testing, verification                       | 09                         |
| Workflow, ownership                         | 01, 10                     |
| Project commands, paths, contracts          | `PROJECT-PROFILE.md`       |
| Domain surfaces                             | `PROJECT-SURFACES.md`      |

## Non-negotiables

- Inertia owns data. Do not add a client cache, global store, or fetching hook.
- Use Wayfinder for product URLs. No hard-coded `/dashboard/...` in new code.
  Server-provided pagination URLs are exempt.
- The server `FormRequest` is the only validation schema. Never duplicate rules
  in the client.
- Page titles use `PageHeader`. Do not add a page-level `<h1>` except for the
  documented auth/public/identity-header exceptions in guide 02.
- There is no `PageContainer` component. Use the exact wrapper string in
  `02-PAGES-LAYOUTS-AND-NAVIGATION.md`.
- Use existing shadcn primitives before writing a component. Never create a
  parallel button, dialog, or field kit.
- Use semantic tokens (`bg-card`, `text-muted-foreground`,
  `text-danger-soft-foreground`, `var(--chart-*)`). No raw palette for status.
- Every icon-only control needs an accessible name.
- Every mutation passes `preserveScroll: true` unless resetting scroll is the
  intent.
- Validation errors are inline (`FieldError`, `FormErrorSummary`), never toasts.
- No page-level horizontal scroll at 390 px. The table itself may scroll, the
  page may not.
- Before claiming done: `npm run types:check`, focused `npx vp check --no-fmt`,
  the relevant `npx vp test` files, and a visual proof on the running app.

## Canonical page anatomy

Order is fixed; each block is optional unless marked required.

1. `<Head title="…" />` — required.
2. Max-width wrapper — required (see guide 02 for the exact classes).
3. `PageHeader` — required for product pages; auth and public pages differ.
4. KPI row — optional, one number per card, no second chart inside.
5. Toolbar — optional; search, filters, primary action.
6. Main content — required: a table, list, editor, or settings sections.
7. Pagination — only below a paginated surface.
8. Closing notes/actions — optional, one privacy or policy note at most.

Do not add an eyebrow, decorative icon, or duplicate title above `PageHeader`.
Detail pages with an avatar/badge/back-link identity block may use a bespoke
header; see guide 02 for the exception list.

## Definition of done

A change is done when all of these are true:

- The requested behavior works on the running app at 1440, 1024, 768, and
  390 px where the surface is responsive.
- No console errors and no page-level horizontal scroll at 390 px.
- Keyboard focus is visible; icon-only controls have names; validation errors
  are announced.
- `npm run types:check` exits 0.
- Focused `npx vp check --no-fmt <changed paths>` exits 0.
- The relevant tests pass; a new shared utility has a test when its edge cases
  are not obvious.
- `npm run build` passes when routing, Wayfinder usage, or the CSS entry
  changed.
- `docs/DESIGN.md` or `PROJECT-PROFILE.md` is updated if a shared rule moved.
- Pack changes: `node frontend-instructions/scripts/check-pack.mjs` exits 0.

Full workflow and reporting format: `10-AGENT-WORKFLOW.md`.

## New-project adaptation

Copy the folder, then replace `PROJECT-PROFILE.md` and
`PROJECT-SURFACES.md`. Keep the guides for this stack. Add the snippet from
`AGENTS_FRONTEND_SNIPPET.md` to the project's `AGENTS.md`. See `README.md` for
the full checklist.
