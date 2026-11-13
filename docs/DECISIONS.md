# Decisions log

Later client decisions. Where these conflict with `docs/requirements.md`, **this file wins**.

| # | Date | Decision | Replaces |
|---|------|----------|----------|
| D-01 | 2026-11-01 | No IRD CBMS real-time sync. IRD invoice format still applies if VAT registered. | — |
| D-02 | 2026-11-01 | VAT and service charge default to **0%** until a Super Admin changes them. Bills always print both lines, e.g. `VAT (0%) 0.00`. Never show "off". | — |
| D-03 | 2026-11-01 | Discount on every sale as **% or fixed Rs**; above the cashier limit (default 10%) needs a manager PIN; every discount is audited with reason. | — |
| D-04 | 2026-11-04 | **English only.** Nepali labels and Nepali UI language removed. (Bikram Sambat dates stay for fiscal year and reports.) | FR-14.4 language, §8 "English and Nepali" |
| D-05 | 2026-11-04 | **No dynamic QR.** QR payments use the printed Fonepay/eSewa QR; staff check the merchant app and tick "I have checked", optional transaction ID. | FR-4.10 |
| D-06 | 2026-11-04 | **Login = username + password for every role.** A 4-digit PIN is optional for quick switching, only on registered terminals; 3 wrong PINs locks PIN until password sign-in. Super Admin never uses PIN. | Role table "PIN" logins |
| D-07 | 2026-11-13 | Group split payment: by amount or by items; each person pays cash / QR or puts their part **on due**. Bill prints PAID NOW, ON DUE and a DUE ACCOUNT block with signature line. | — |
| D-08 | 2026-11-13 | Reports export to **Excel (.xlsx)** and **PDF**. | — |
| D-09 | 2026-11-13 | UI follows the approved WoodStone design canvas (white/orange, Inter, icon rail), not the govnepal look. | govnepal design system |
