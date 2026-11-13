# WoodStone RMS — step-by-step build plan

One step = one branch = one pull request. A step is **done** only when every gate below is green.
Sources: `docs/requirements.md` (FR IDs), `docs/DECISIONS.md` (wins on conflict), design canvas "WoodStone POS Design".

## Gates for every step

**1. Plan (before code)** — the PR description lists: scope (FR IDs), tables touched, routes, roles allowed, and what is _out_ of scope.

**2. Automated QA (GitHub Actions, must be green before merge)**

- Pint (code style), Larastan level 7, `vue-tsc`, `vp check` (lint/format)
- Feature tests on SQLite **and** PostgreSQL 16 (production database)
- `composer audit` and `npm audit` (runtime, high+)
- Every new route has at least: an allowed-role test, a forbidden-role test (403), a guest test (redirect), and a validation test.

**3. Security checklist (reviewed in the PR)**

- [ ] Authorization on every route/action (policy or role middleware) — never only hidden buttons
- [ ] Validation through FormRequest; no mass assignment of `role`, `is_active`, money totals
- [ ] Money as `DECIMAL(12,2)` / integer paisa maths, never float; totals recomputed on the server
- [ ] Bills, payments, stock and ledger rows append-only; corrections are reversal rows
- [ ] Sensitive actions (void, discount, refund, price change, role change, PIN/password reset) → audit log
- [ ] Rate limits on login, PIN, and any public endpoint (guest QR call)
- [ ] No secrets in code, logs or responses; `.env` only
- [ ] Output escaped (Vue default; no `v-html` with user data)

**4. Manual QA** — a short click-through script in the PR, run on the Mac with `bin/qa`.

## Steps

| Step | Scope                                                                                                                                                             | Main FR / decisions    | Status  |
| ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------- | ------- |
| 0    | Baseline: Laravel 13 + Vue starter, CI (SQLite + PostgreSQL + audits), docs                                                                                       | 9A                     | ✅      |
| 1    | **Auth, users, roles**: username + password, optional PIN on registered terminals, role permissions, user admin, owner setup command, audit log, security headers | §4, D-06               | PR open |
| 2    | **Menu**: categories (one sub-level), items, price/cost/margin, photos, sold-out, time windows, extras, stations                                                  | FR-3                   | 🔨      |
| 3    | Floor: zones, tables, cabins, statuses, move/merge, reservations                                                                                                  | §3, FR-1               |         |
| 4    | Settings: restaurant profile, fiscal year (BS), bill numbering, VAT/service 0%, discount limit, payment methods, printers                                         | FR-14, D-02, D-05      |         |
| 5    | POS orders + KOT: open order, add items, send KOT, void with reason + PIN, takeaway/delivery, hold                                                                | FR-2                   |         |
| 6    | Kitchen & bar display (realtime, Reverb)                                                                                                                          | FR-5                   |         |
| 7    | Billing: discount %/Rs, payments (cash, manual QR, card, due), split by amount/items, bill print 80 mm, reprint COPY                                              | FR-4, D-03, D-05, D-07 |         |
| 8    | Customers, groups & dues ledger: receive, move due, limits                                                                                                        | FR-10, M15, D-07       |         |
| 9    | Stock & purchase: items, recipes, stock movements, POs, suppliers                                                                                                 | FR-6/7                 |         |
| 10   | Cash book, expenses, payables & receivables, day open/close                                                                                                       | FR-8/9, M15            |         |
| 11   | Reports + Excel/PDF export                                                                                                                                        | §6, D-08               |         |
| 12   | Owner dashboard (live tables, charts, filters)                                                                                                                    | FR-11                  |         |
| 13   | Staff, attendance, payroll                                                                                                                                        | FR-12                  |         |
| 14   | Waiter phone app, guest QR menu, call waiter                                                                                                                      | M16                    |         |
| 15   | Hardening: backups, monitoring, deploy to server, load test, UAT with client                                                                                      | 9A                     |         |

## Definition of done (per step)

1. CI green on the PR (all three jobs).
2. Security checklist ticked in the PR.
3. Manual QA script passed on the Mac.
4. Owner approves → merge to `main`.
