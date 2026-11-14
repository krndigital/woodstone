# User acceptance test script (step 15)

Run on staging with the owner and one cashier, waiter and cook. Tick each line; any "no" is a bug ticket.

## Floor, POS, kitchen

- [ ] Waiter opens Table G1 for 3 guests, adds 2 Momo + 1 Coke with an extra; tap Send KOT → kitchen and bar each get one KOT.
- [ ] Kitchen display: tap item New → Cooking → Ready; "Ping waiter" rings the waiter's screen.
- [ ] Waiter voids a sent item: refused without manager; works with manager username + PIN; shows in audit report.
- [ ] Move G1 to G2, merge G3 into G2, cancel an empty order.
- [ ] Takeaway order prints TOKEN on KOT and bill.

## Billing and dues

- [ ] 10 % discount works for cashier; 20 % asks for manager.
- [ ] Group split by items: 3 people, cash / QR (tick) / due. Bill prints PAID NOW, ON DUE, DUE ACCOUNT, signature.
- [ ] Reprint shows COPY. Partial return and full cancel need Admin; cash book shows refund.
- [ ] Customer over due limit needs manager. Receive due; move due to another customer.

## Money

- [ ] Open day with opening cash, sell, add an expense with receipt photo, close day: difference correct.
- [ ] Cash book: sale, due receipt, purchase payment, expense, transfer cash → bank all appear.
- [ ] Monthly rent reminder shows and is confirmed once.

## Stock

- [ ] Purchase 5 kg chicken at Rs 600 → stock 5000 g, average cost 0.60/g; paid part shows owed on supplier.
- [ ] Recipe Momo 150 g chicken → paid bill lowers stock by 150 g per plate; cost/margin shown.
- [ ] PO → receive part with a different rate → difference flagged; pay supplier; return goods.
- [ ] Wastage and stock count recorded with reason.

## Staff

- [ ] Staff checks in with PIN on the counter tablet; late after shift + 10 min.
- [ ] Leave approved; salary sheet shows absent days, advance deducted; paying posts a Salary expense; payslip prints.

## Reports, dashboard, QR

- [ ] Daily sales totals equal bill list, cash book and day close for the same day.
- [ ] Every report exports to Excel and PDF.
- [ ] Owner dashboard: today vs this month, running tables, top items.
- [ ] Guest scans table QR on a phone (no login): menu shows, Call waiter rings staff, guest sees "Sunita is coming".
- [ ] Regenerated QR makes the old card stop working.

## Pre-launch checks

- [ ] Printer: KOT and bill print on the 80 mm printer; printer unplugged → order still saved, reprint later.
- [ ] Internet down for 2 minutes → pages recover after reconnect, no lost bills.
- [ ] Backup restored on a separate database and checked.
- [ ] Each role tried on a page it must not see → 403.
