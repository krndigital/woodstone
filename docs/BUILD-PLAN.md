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

| Step | Scope                                                                                                                                                             | Main FR / decisions    | Status |
| ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------- | ------ |
| 0    | Baseline: Laravel 13 + Vue starter, CI (SQLite + PostgreSQL + audits), docs                                                                                       | 9A                     | ✅     |
| 1    | **Auth, users, roles**: username + password, optional PIN on registered terminals, role permissions, user admin, owner setup command, audit log, security headers | §4, D-06               | ✅     |
| 2    | **Menu**: categories (one sub-level), items, price/cost/margin, photos, sold-out, time windows, extras, stations                                                  | FR-3                   | ✅     |
| 3    | Floor: zones, tables, cabins, statuses, move/merge, reservations                                                                                                  | §3, FR-1               | ✅     |
| 4    | Settings: restaurant profile, fiscal year (BS), bill numbering, VAT/service 0%, discount limit, payment methods, printers                                         | FR-14, D-02, D-05      | ✅     |
| 5    | POS orders + KOT: open order, add items, send KOT, void with reason + PIN, takeaway/delivery, hold                                                                | FR-2                   | ✅     |
| 6    | Kitchen & bar display (realtime, Reverb)                                                                                                                          | FR-5                   | ✅     |
| 7    | Billing: discount %/Rs, payments (cash, manual QR, card, due), split by amount/items, bill print 80 mm, reprint COPY                                              | FR-4, D-03, D-05, D-07 | ✅     |
| 8    | Customers, groups & dues ledger: receive, move due, limits                                                                                                        | FR-10, M15, D-07       | ✅     |
| 9    | Stock & purchase: items, recipes, stock movements, POs, suppliers                                                                                                 | FR-6/7                 | ✅     |
| 10   | Cash book, expenses, payables & receivables, day open/close                                                                                                       | FR-8/9, M15            | ✅     |
| 11   | Reports + Excel/PDF export                                                                                                                                        | §6, D-08               | ✅     |
| 12   | Owner dashboard (live tables, charts, filters)                                                                                                                    | FR-11                  | ✅     |
| 13   | Staff, attendance, payroll                                                                                                                                        | FR-12                  | ✅     |
| 14   | Waiter phone app, guest QR menu, call waiter                                                                                                                      | M16                    | ✅     |
| 15   | Hardening: backups, monitoring, deploy to server, load test, UAT with client                                                                                      | 9A                     | ✅     |

## Notes on finished steps

- **6 Kitchen display** polls every 8 s. Reverb push can replace polling later without changing the controller.
- **7 Billing**: bill cancel / sales return with Admin approval (FR-4.4, FR-4.8), day open/close (FR-4.6) and takeaway token / packaging charge (FR-4.7) move to step 10.
- **9 Stock**: supplier payments and payable ledger (FR-6.3), purchase returns (FR-6.7), batches and expiry (FR-7.8) and store-to-kitchen issue (FR-7.7) move to step 10. Stock may go below zero after sales; the stock page shows it in red so the store keeper can count.

- **10 Money**: cash book, expenses with receipts and monthly reminders, day open/close, supplier payments and returns, bill return/cancel, takeaway token. Day open is recommended, not forced, so service never stops.
- **11 Reports**: 15 reports, Excel (.xlsx, built in) and PDF (print page → Save as PDF). Dates are AD; Bikram Sambat display is a follow-up.
- **12 Dashboard**: owner KPIs refresh every 30 s.
- **13 Staff**: attendance by PIN, leave, advances, salary sheet; salary payment posts a Salary expense.
- **14 Waiter app / QR**: the POS is installable on phones (PWA); guest QR page with call waiter, ringing staff banner, kitchen ping. Guest self-ordering is not included (D-decision pending).
- **15 Hardening**: CSP, health check with database, daily backup command + schedule, `docs/DEPLOY.md`, `docs/UAT.md`, k6 load script. Server install, monitoring account and UAT with the client happen on site.

### Gap work after step 15 (all CI green)

- **Bikram Sambat**: BS dates on bills, KOT and daily report; fiscal year worked out from BS (FR-14).
- **Billing extras**: counter sale, bank transfer, round-off setting, complimentary (approval), due receipt print, customer statement + WhatsApp link.
- **Loyalty**: points earn/redeem/expiry, Silver/Gold tiers, visit history, birthdays (FR-10.5/10.6).
- **Accounting**: automatic double-entry ledger, trial balance, balance sheet, cash flow, manual journals, payables ageing, opening balances (FR-13, FR-15).
- **Floor**: bookings with advance (used on the bill, refunded or kept), cabin minimum spend, item status history, kitchen-times and guest-call reports, guest calls routed to the table's waiter (FR-1.4, FR-1.5, FR-3.5, FR-16).
- **Stock**: expiry date per purchase line, "expiring within 7 days" alert on the stock page (FR-7.8, light version).
- **Still open**: store-to-kitchen issue (FR-7.7) needs separate store/kitchen stock locations — one pool today; strict first-expiry-first-out batch costing (FR-7.8 full); design-canvas look (D-09).

## Definition of done (per step)

1. CI green on the PR (all three jobs).
2. Security checklist ticked in the PR.
3. Manual QA script passed on the Mac.
4. Owner approves → merge to `main`.
