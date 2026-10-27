# LinkPilot Full-Parity Implementation Plan — Phases 5–10

**Date:** 2026-10-22  
**Authority:** `PRD.md` §§7–12 and the matching `product/features/*.json` requirement catalogs. Existing 2026-10-19 plans are historical; they are not execution authority where they conflict with the current PRD, catalogs, or the user’s deferred workspace-collaboration scope.

## Goal and boundaries

Deliver every remaining approved Phase 5–10 requirement end to end. Do not add new workspace collaboration, invitations, or role types. Preserve existing active-workspace authorization and resource isolation. Use `blocked_external` only for operator-controlled prerequisites that cannot be verified locally; complete every reachable local behavior.

Each phase closes only after implementation, docs/catalog evidence, focused tests, full regression gates, isolated browser smoke, and three independent reviews: regular-user UX, admin UX/authorization, and PRD alignment. Fix actionable findings and obtain a fresh review of those findings before advancing. Phase 10 repeats all three reviews across the complete SaaS.

## Phase sequence and scope

| Phase | Requirements and outcome                                                                                                                                                                                                                              | Exit condition                                                                                                                                                       |
| ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 5     | `short-links`, `folders`, `qr-codes`: complete LNK-1–14, FOL-1–4, QR-1–6 and their acceptance criteria. Advanced delivery, governance, query merging, CSV/bulk actions, folder rollups, and persistent QR campaigns all work end to end.              | Data migration preserves current links/QR behavior; user workflows, public guards, export/import, and QR scan counters pass.                                         |
| 6     | `bio-pages`, `link-pages`, `overlays`, `tracking-pixels`, plus the media foundation and lead/file, variant, and pixel-trigger workflows listed in their PRD/catalog contracts.                                                                        | Workspace-owned media paths, upload safety and limits, publishing, responsive previews, public rendering, scheduling, and consent-aware pixel triggers are verified. |
| 7     | `analytics`, `api-keys`, `webhooks`, `notifications`: advanced insights, versioned API, scoped credentials, webhook operations/redelivery, and notification center.                                                                                   | Authorization, secret handling, queue delivery/retry, privacy, pagination, and failure states are exercised locally.                                                 |
| 8     | `billing`, `auth-account`: Cashier/Stripe subscription lifecycle, invoices/tax surfaces, account settings, and account deletion.                                                                                                                      | Local billing contracts and destructive-action lifecycle pass; unavailable live Stripe checks remain explicitly external rather than simulated.                      |
| 9     | `admin-insights`, `admin-plans-subscriptions`, `admin-users-roles`, `admin-content`, `admin-settings`, `admin-files-pages-localization-logs`: permission-based administration, auditability, cross-owner metadata, and every catalogued admin screen. | Separate admin shell, policies, high-impact confirmations, role authorization, and admin workflows pass. No new workspace role system.                               |
| 10    | Reconcile migrations/data, remove obsolete paths, finish operational docs and product records, run complete gates, and perform final independent user/admin/PRD review.                                                                               | No stale aliases/shims or unresolved in-scope blockers; product evidence matches verified behavior; all three final reviews pass.                                    |

## Execution protocol

1. Before changing a phase, read its product feature records and the relevant PRD section; use existing routes, policies, request validation, workspace scoping, UI components, and tests as the contract baseline.
2. Write a phase-specific executable plan where the subsystem has multiple dependent changes. Keep independently deployable modules in separate plans and state dependencies explicitly.
3. For each uncertain consumer-visible behavior, add a permanent regression test that fails for the missing behavior before production changes. Avoid tests that only pin forwarding, incidental copy, source text, or mock echoes.
4. Preserve exact resource authorization at every route, query, mutation, export, import, file reference, and public delivery boundary. Never trust a client-provided workspace ID or untrusted proxy header.
5. Run focused backend/UI checks, then the complete relevant automated suite once after implementation/review fixes. Run browser scenarios only against the disposable isolated app and isolated data; do not stop or mutate the shared app on port 8000.
6. Update `PRD.md`, feature records, `product/progress.json`, screenshots/evidence manifests where relevant, and operational docs only with observed evidence. Do not mark a requirement verified on tests or source inspection alone when the acceptance surface requires browser behavior.
7. Obtain three independent phase reviews after fixes: user UX, admin UX/authorization, and PRD alignment. Reviewers must not receive test credentials or secret material.

## Global verification gates

- Focused behavior tests cover allowed and denied authorization, valid and forged/cross-workspace inputs, guards-before-side-effects, quota/boundary behavior, and state transitions.
- Full Laravel test suite passes after each phase; product catalog/PRD mapping test passes after documentation changes.
- Frontend formatting/lint, type checking, and production build pass after frontend/route changes.
- Migrations run against a persistent disposable SQLite database and preserve representative pre-migration data; destructive or irreversible changes require explicit assertions and rollback analysis.
  Browser smoke covers actual user and admin surfaces at desktop and narrow mobile sizes, keyboard flows, public routes, and primary success/error states. Every phase also rechecks the mandated shell keyboard paths: global-search shortcut/results/Escape focus restoration and mobile-drawer focus containment/restoration. No browser smoke uses the developer’s shared persistent database.
- Operational docs identify real external prerequisites and fail-closed behavior; no fake provider responses, credentials, or implementation fallbacks.

## External prerequisites to preserve

The PRD’s §12 operator-owned Stripe credentials/prices/tax policy, privacy copy/hash configuration, DNS/certificate automation, durable production media storage, and production ClamAV remain real prerequisites. Implement all local contracts and report those exact external gates; never simulate successful provider or malware-scan outcomes.

## Phase-specific execution plans

- Phase 5 short links/folders: `2026-10-22-linkpilot-phase-5-advanced-links-and-folders.md`.
- Phase 5 QR campaigns: `2026-10-22-linkpilot-phase-5-qr-campaigns.md` (depends on the short-link delivery guards).
- Write the Phase 6–9 detailed plan immediately before each phase using the current matching PRD/catalog contract and this roadmap. Phase 10 closes the whole roadmap.
