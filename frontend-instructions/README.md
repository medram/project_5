# Frontend instruction pack

## What this pack is

Engineering patterns for the LinkPilot frontend: pages, layouts, forms, lists,
feedback, accessibility, and verification. It is not a visual spec.
`docs/DESIGN.md` remains the binding authority for tokens, typography,
surfaces, and composition. This pack decides **how** to implement a surface,
not **what it should look like**.

The pack is portable. The guides are stack-scoped and stable; project facts
live in `PROJECT-PROFILE.md` and product-specific surfaces in
`PROJECT-SURFACES.md`. A new Laravel + Inertia + React project copies the
folder and replaces those two files.

## Who reads what

| Reader | Start here | Then |
|---|---|---|
| Coding agent | `FRONTEND_INSTRUCTIONS.md` | The task guide it routes to, then the owning code |
| Human reviewer | `README.md` | `PROJECT-PROFILE.md` for commands, the relevant guide for rules |
| New project setup | "Portability" below | `PROJECT-PROFILE.md`, `PROJECT-SURFACES.md`, `AGENTS_FRONTEND_SNIPPET.md` |

## File map

```text
frontend-instructions/
├── README.md                          human entry point and portability manual
├── FRONTEND_INSTRUCTIONS.md           agent entry point, stack rules, task router
├── AGENTS_FRONTEND_SNIPPET.md         paste-ready AGENTS.md block
├── PROJECT-PROFILE.md                 this project's commands, paths, contracts
├── PROJECT-SURFACES.md               LinkPilot product surfaces
├── verified.json                       last full audit, pack hash, conformance baseline
├── scripts/check-pack.mjs              deterministic pack and conformance checker
├── scripts/check-pack.test.mjs         checker behavior and drift regression tests
└── frontend-guides/
    ├── 01-ARCHITECTURE-AND-OWNERSHIP.md
    ├── 02-PAGES-LAYOUTS-AND-NAVIGATION.md
    ├── 03-DATA-ROUTES-AND-PERMISSIONS.md
    ├── 04-FORMS-AND-VALIDATION.md
    ├── 05-TABLES-AND-LISTS.md
    ├── 06-COMPONENTS-AND-STYLING.md
    ├── 07-FEEDBACK-STATES-AND-OVERLAYS.md
    ├── 08-ACCESSIBILITY-AND-RESPONSIVE.md
    ├── 09-TESTING-AND-VERIFICATION.md
    ├── 10-AGENT-WORKFLOW.md
    └── 11-RECIPES.md
```

## How an agent uses it

Paths in the guides are shorthand: `pages/…`, `components/…`, `layouts/…`,
`hooks/…`, `lib/…`, and `types/…` are relative to `resources/js`; `app/…`,
`tests/…`, `database/…`, and `docs/…` are relative to the repository root.
`PROJECT-PROFILE.md` carries the full map.

1. Read `FRONTEND_INSTRUCTIONS.md` in full. It is short.
2. Use its task router to open the mandatory guides for the task.
3. Read `PROJECT-PROFILE.md` when you need commands, paths, shared props, or
   the layout registry.
4. Read the owning code before editing. The guides describe patterns; the code
   is the current implementation.
5. Verify with `09-TESTING-AND-VERIFICATION.md` before claiming completion.

If code and a guide disagree, follow the guide for new code and report the
deviation. Do not silently copy a legacy pattern from the file you happen to
open.

## Portability

To reuse the pack in another Laravel + Inertia + React + Tailwind + shadcn/ui
project:

1. Copy the whole `frontend-instructions/` folder.
2. Rewrite `PROJECT-PROFILE.md` for the new repository: versions, commands,
   path map, layout registry, shared props, route helpers, verification setup.
3. Rewrite `PROJECT-SURFACES.md` for the new product. If it has no equivalent
   surfaces, keep the file and state that explicitly.
4. Keep the guides. They encode this stack, not this product.
5. Add the block from `AGENTS_FRONTEND_SNIPPET.md` to the new `AGENTS.md`.
6. Replace project-specific examples and recipe sources with verified local ones.
7. Run the verification commands in the new repository and fix drift in the
   profile before relying on the pack.

A different stack (Vue, Svelte, Livewire-only, or a client-side router)
requires rewriting guides 01–03 and the recipes. Do not pretend the folder is
stack-agnostic.

## Maintenance

- Update `PROJECT-PROFILE.md` when a command, path, version, shared prop, or
  layout rule moves.
- Update `PROJECT-SURFACES.md` when a domain surface gains or loses an entry
  file, data contract, or shared component.
- Update a guide when a repeated pattern changes. A one-off page does not
  justify a guide edit; a second consumer does.
- Record known debt in the guide that owns the rule. Do not silently diverge.
- Keep guides 01–10 at or below 250 lines and `11-RECIPES.md` at or below 450.
  The checker allows 10 lines of review tolerance on guides 01–10. The recipe
  file may run longer because each recipe is a self-contained skeleton; split it
  only when a recipe gains a second variant. The checker enforces these budgets.
- Run `node frontend-instructions/scripts/check-pack.mjs` after any pack edit.
- Run `--changed` before a feature audit to see which guides the code moved.
- Update `verified.json` only after a full audit, never after a partial edit.
- When a path or command moves, update `PROJECT-PROFILE.md` and the impact map
  first, then the guides that cite it.

## AGENTS.md integration

`AGENTS.md` carries a short pointer to this pack, not the rules themselves.
Future projects paste `AGENTS_FRONTEND_SNIPPET.md` into their agent guide so a
fresh agent knows where the engineering rules live.

## Limits

- No credentials in this pack. Seeded local accounts are described only by
  their source file.
- Never edit generated Wayfinder output under `resources/js/routes`,
  `resources/js/actions`, or `resources/js/wayfinder`.
- `resources/js/components/ui` is shadcn-derived and locally themed. It is
  excluded from lint/format and follows the ownership rule in
  `06-COMPONENTS-AND-STYLING.md`; it is not disposable.
- This pack does not replace `docs/DESIGN.md`. When they conflict on visuals,
  design wins. When they conflict on engineering, this pack wins for new code.
