# LinkPilot product ledger

This directory is the durable, machine-readable product contract that complements the narrative `PRD.md`. Product engineering owns the records; the product owner approves scope changes; designated reviewers approve evidence-based status changes.

## Structure

- `catalog.json` is the ordered feature index.
- `features/*.json` contains one feature record per catalog entry and validates against `schema/feature.schema.json`.
- `progress.json` is the current cross-feature status and verification summary.
- `reference/belink/inventory.json` records the untrusted static bundle inventory, observed navigation, runtime limitation, immutable PNG checksums, evidence rulings, and the reference-to-LinkPilot route/state screenshot matrix.
- `reference/belink/manifest.json` inventories retained reference screenshots, including captures excluded from evidence.
- `reference/belink/screenshots/` is immutable reference evidence. Implementation screenshots must be stored separately and must not overwrite it.

## Allowed states

- `catalogued`: explicit future or deliberately non-target scope retained for product decisions.
- `planned`: approved implementation work that has not yet reached its completion gate.
- `in_progress`: an existing foundation or active slice is being corrected, migrated, or expanded and is not yet fully proved.
- `blocked_external`: repository work and local proof are complete, but a named credential, provider, policy, DNS/TLS, or other operator-controlled prerequisite prevents the live check.
- `verified`: the complete feature has current implementation evidence and has passed every required implementation, migration, automated, browser, and independent-review gate.

Status is conservative. Narrative labels such as “Delivered” do not automatically become `verified`. A feature and its progress record must agree. Requirement records may be less advanced than their containing feature when part of the scope remains future or intentionally excluded.

## Update rules

1. Keep every feature ID in `catalog.json`, `progress.json`, and its matching filename in the test-defined order.
2. Keep the exact feature top-level keys and nested key order defined by the schema and catalog test.
3. Assign every `PRD.md` requirement ID to exactly one feature. New requirements use a unique, feature-specific uppercase prefix and are never reused.
4. Update routes, dependencies, prerequisites, acceptance criteria, and evidence in the same change as the behavior they describe.
5. Do not mark a record `verified` from code inspection, stale test output, a reference screenshot, or a partially implemented workflow. Add dated implementation evidence only for checks actually exercised against the current revision.
6. `blocked_external` requires both a precise missing prerequisite in `blockers` and completed local proof in `checks`; it is not a substitute for unfinished repository work.
7. After each implementation phase, change only the affected requirement/feature states and corresponding evidence. Never bulk-promote untouched records.
8. Clean cutovers remove obsolete records and routes only after every caller, test, and migration path has moved.

## Screenshot evidence and privacy

Reference screenshots document visible hierarchy, controls, density, responsive behavior, and interaction states only. They do not prove server behavior. Evidence descriptions must state only what the image visibly shows. Every retained PNG is inventoried with role, route/state, viewport, capture time, privacy note, and observed behavior. Captures with the wrong state or visible token-like identifiers are marked `excluded` and must not be referenced by feature records.

Do not commit live customer emails, IP addresses, credentials, access tokens, webhook secrets, or personal values. Crop or irreversibly blur sensitive values before committing a new image. Record the redaction in the manifest. Do not modify or delete the retained BeLink PNGs; replace invalid evidence with a new separately named capture and then update the manifest.

The BeLink inventory is descriptive evidence only. Static route and lazy-module entries do not prove runtime behavior, and the route/state matrix maps visible reference hierarchy to LinkPilot-owned routes and required implementation captures without changing feature status. When a shared demo state is unavailable, record the exact limitation as `unverified — confirm first`; never invent or reuse credentials to fill the gap.

## Verification and reviewer gate

A feature reaches `verified` only after its implementation is complete, migrations are proved on representative data, focused and full automated checks pass, the real application is exercised in desktop and mobile browsers, and the ledger contains current evidence for those results. External live checks may remain narrowly `blocked_external` only when local contract/fake-transport proof is complete.

Each phase then requires three independent reviews:

1. **User UX reviewer** exercises changed regular-user workflows at desktop and mobile, including keyboard, focus, validation, empty/loading, and responsive states, and compares them with retained reference behavior.
2. **Admin UX reviewer** verifies the distinct admin shell, permissions, responsive table/card behavior, operational workflows, and denial to ordinary users.
3. **PRD alignment reviewer** maps each changed acceptance criterion to runtime code, migration, test, browser, screenshot, and ledger evidence and rejects stale or overstated claims.

All actionable in-repository findings must be fixed and freshly re-reviewed before the phase advances. An unaffected reviewer still performs a regression pass. Missing external prerequisites are documented narrowly; they never justify a fake success state or an unfinished local implementation.
