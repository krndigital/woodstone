# WoodStone Restaurant Management System — Requirements

Nov 1, 2026 · @kiran kandel

## 1. Overview and goals

WoodStone needs one web-based system to replace its Google Sheets workbook. The system will cover table and cabin ordering, kitchen (KOT), POS billing with thermal printing, expenses, cash flow, staff and reports. It is built by Softechpark for Woodstone Restaurant & Cafe, Butwal-08, Sukkhanagar: one restaurant, one database, with table, cabin, open-green-space and takeaway service. All dates are in Bikram Sambat (BS) and all money is in NPR.

**Business goals**

1. Every order is taken on a tablet or mobile at the table or cabin and goes straight to the kitchen or bar. No paper and no re-typing.
2. Every rupee in and out is recorded once and rolls up automatically into daily sales, cash flow, kitchen profit/loss and a balance sheet.
3. The owner can see revenue by day and month, best-selling items, item-wise profit and operating cost from a phone without opening a spreadsheet.
4. Customers scan a table QR code and see the live menu on the WoodStone website.
5. Staff, attendance and salary are managed in the same system.

**Scope of this document:** analysis and requirements only. No code is written at this stage. Section 10 holds the prompt that will drive development once this document is approved.

### Client checklist coverage

All 15 items on the client's list are covered. Customer loyalty and purchase orders were added after the check.

| #   | Client item                                     | Covered in                          | Status                                          |
| --- | ----------------------------------------------- | ----------------------------------- | ----------------------------------------------- |
| 1   | Dashboard: daily sales, profit, expenses        | Section 6 owner dashboard           | Covered                                         |
| 2   | POS billing: orders, invoices, discounts        | M2, M4                              | Covered                                         |
| 3   | Table management: booking, occupied/free        | M1, Section 3                       | Covered                                         |
| 4   | Kitchen management: KOT, preparation status     | M3                                  | Covered                                         |
| 5   | Menu management: items, categories, prices      | M5                                  | Covered                                         |
| 6   | Inventory: stock, raw materials, wastage        | M7                                  | Covered                                         |
| 7   | Purchase management: suppliers, purchase orders | M6                                  | Added purchase orders (FR-6.5, FR-6.6)          |
| 8   | Cash flow: cash in, cash out, daily balance     | M9                                  | Covered                                         |
| 9   | Accounting: income, expenses, liabilities       | M13                                 | Covered                                         |
| 10  | Staff management: attendance, salary, shifts    | M12                                 | Covered                                         |
| 11  | Customer management: profiles, loyalty          | M10                                 | Added profiles and loyalty (FR-10.4 to FR-10.7) |
| 12  | Reservation: table and cabin bookings           | FR-1.4                              | Covered                                         |
| 13  | Reports: daily, weekly, monthly                 | Section 6                           | Covered                                         |
| 14  | Tax and billing: VAT, PAN, invoice records      | FR-4.1, FR-4.3, FR-4.4, Section 8.1 | Covered                                         |
| 15  | User access: owner, manager, cashier, kitchen   | Section 4                           | Covered (plus waiter, bartender, store keeper)  |

## 2. How WoodStone works today

WoodStone runs everything in one Google Sheets workbook with about 9 tabs. Only the Sales and Kharcha tabs are actually filled in every day. Kitchen P/L, cash flow and the credit columns are set up but still empty. This means the owner cannot see profit, cash position or credit (udharo) today.

### 2.1 Sheet-by-sheet field analysis

| Tab              | Fields today                                                        | What it is used for              | Observations                                                                                                                                                                                                                                                                                                               | Replaced by module                             |
| ---------------- | ------------------------------------------------------------------- | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| Sales            | Date (BS, e.g. 2082-10-01), Sales amount, Total (monthly)           | One total per day, typed by hand | Magh 2082 total was Rs 258,395 over 29 days, about Rs 8,900 per day (range Rs 2,075 to Rs 20,695). No item, table, payment-mode or staff detail. Monthly total is a manual row.                                                                                                                                            | POS, Daily sales, Revenue reports              |
| Sales (cols F–I) | Credit amount, Total credit, Credit received amount, Total received | Credit (udharo) tracking         | Columns exist but are empty. No customer name or due date.                                                                                                                                                                                                                                                                 | Customer credit ledger                         |
| Kharcha          | Date, Heading (free text), Amount (Rs), Total amount                | Every purchase and expense       | About 2,000 rows. Date typed only on the first row of each day. Free-text items with typos ("Fiench fries", "Blck"). Kitchen ingredients (chicken, milk, chowmein), resale stock (Shikher, Surya, Highlander, Panmint, Coke, Sprite, beer), gas, and construction (stone Rs 9,800, block Rs 7,000) are all mixed together. | Purchase, Inventory, Expense categories, Capex |
| kitchen amdani   | Date, Kitchen Amdani, Amount, Kitchen kharcha, Amount               | Kitchen income vs kitchen cost   | Empty. Kitchen income cannot be separated today because sales have no item detail.                                                                                                                                                                                                                                         | Kitchen P/L (automatic)                        |
| CASH FLOW        | Date, Cash inflow, Cash outflow, Total amount                       | Cash position                    | Empty.                                                                                                                                                                                                                                                                                                                     | Cash book / Cash flow                          |
| RENT             | Date, Month (Nepali month), Rent, Electricity, PANI supply, Status  | Fixed operating cost             | Rent Rs 25,000 per month from Asar 2082. First 3 months tagged "investment" (setup period), later "expense". Electricity and water columns are empty.                                                                                                                                                                      | Operational cost / recurring expenses          |
| bank deposit     | Not visible in screenshots                                          | Cash deposited to bank           | To confirm fields.                                                                                                                                                                                                                                                                                                         | Bank account ledger                            |
| Investment       | Not visible                                                         | Owner capital put in             | To confirm fields.                                                                                                                                                                                                                                                                                                         | Capital / owner equity                         |
| esewa karobar    | Not visible                                                         | eSewa wallet transactions        | To confirm fields.                                                                                                                                                                                                                                                                                                         | Payment modes + wallet ledger                  |

### 2.2 What the photos show

- **Outdoor garden:** stone path on gravel, about 6–8 open tables with metal benches (blue cushions) and glass-top tables, plus tyre seats. Prayer flags and string lights.
- **Outdoor cabins:** a row of 3 wooden cabins with curtain doors and small shutter windows (Cabin 1, 2, 3). These are private, closed seating.
- **Indoor hall:** gravel floor with stepping stones, a wooden enclosed booth (a 4th cabin), low tables with woven moodas, fans and lanterns.
- **Counter / bar:** front counter with a cashier seat, drinks fridge, bottled water stock, cigarettes and a back bar with liquor and glasses. Fonepay/QR codes are displayed on the wall.

### 2.3 Pain points the system must fix

1. Sales are a single number per day, so best-selling items, per-table revenue and payment mode split are unknown.
2. Expenses are free text, so kitchen cost, bar cost and construction cannot be separated or totalled by category.
3. No stock control for resale items (cigarettes, beer, soft drinks) that are bought in bulk and sold one by one.
4. Credit (udharo) and cash in hand are not tracked, so cash leakage cannot be detected.
5. No record of which staff took or served an order.
6. Totals and month-ends are manual and error-prone.

## 3. Floor layout: zones, tables and cabins

The floor is modelled as **Zones** that hold **Seats**. A seat is either an open **Table** or a closed wooden **Cabin**. Admin can add, rename, move and disable seats, so the layout is never hard-coded.

| Zone                | Seat type     | Proposed codes | Notes                                       |
| ------------------- | ------------- | -------------- | ------------------------------------------- |
| Garden (outdoor)    | Table         | G1–G8          | Open benches, glass tables, tyre seats      |
| Cabin row (outdoor) | Cabin         | C1, C2, C3     | Closed wooden cabins with curtain doors     |
| Indoor hall         | Table + Cabin | H1–H4, C4      | Wooden booth counted as Cabin 4             |
| Counter / bar       | Counter       | BAR            | Walk-in, takeaway and drinks at the counter |
| Takeaway / delivery | Virtual       | TA             | Orders with no seat                         |

**Seat attributes:** code, name, zone, type (table / cabin / counter / virtual), capacity, QR token, sort order, active flag, optional minimum spend or cabin charge.

**Seat status (colour on floor map):**

1. Free (green)
2. Occupied, order running (orange)
3. Bill printed, waiting payment (blue)
4. Reserved (purple)
5. Needs cleaning (grey)

**Floor operations:** open a seat, merge two tables into one bill, move an order to another seat, split one bill into several, and reserve a seat for a time.

Exact seat counts are an open question (Section 9).

## 4. Users, login levels and roles

There are three login levels: **Super Admin**, **Admin** and **Staff**. Staff logins carry one of five predefined roles. Super Admin can edit what each role may do, but the predefined roles ship ready to use.

| Level       | Role             | Who                                    | Login method                      | Main screens                                                                              |
| ----------- | ---------------- | -------------------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------- |
| Super Admin | Owner            | Restaurant owner / Softechpark support | Email + password, 2FA             | Everything, including settings, users, roles, balance sheet, audit log                    |
| Admin       | Manager          | Restaurant manager                     | Email or phone + password         | Operations, menu, purchase, expenses, staff, reports (no system settings or role editing) |
| Staff       | Cashier          | Counter staff                          | Username + 4–6 digit PIN          | POS, billing, payments, cash in/out, day close                                            |
| Staff       | Waiter / Captain | Floor staff                            | PIN on shared tablet or own phone | Floor map, take order, send KOT, request bill                                             |
| Staff       | Kitchen (Chef)   | Kitchen                                | PIN on kitchen screen             | Kitchen display (KDS): accept, prepare, ready                                             |
| Staff       | Bartender        | Bar                                    | PIN on bar screen                 | Bar display, drinks KOT, bar stock                                                        |
| Staff       | Store keeper     | Purchase / stock person                | PIN                               | Purchase entry, stock in/out, wastage                                                     |

### 4.1 Permission matrix

S = Super Admin, A = Admin. Yes / View / No for staff roles.

| Action                            | S   | A                               | Cashier                      | Waiter         | Kitchen        | Bar            | Store          |
| --------------------------------- | --- | ------------------------------- | ---------------------------- | -------------- | -------------- | -------------- | -------------- |
| Take order, send KOT              | Yes | Yes                             | Yes                          | Yes            | No             | Yes (drinks)   | No             |
| Cancel / void a sent item         | Yes | Yes                             | With reason, needs Admin PIN | No             | No             | No             | No             |
| Apply discount                    | Yes | Yes                             | Up to set % limit            | No             | No             | No             | No             |
| Print bill, take payment          | Yes | Yes                             | Yes                          | Request only   | No             | No             | No             |
| Edit or delete a paid bill        | Yes | View                            | No                           | No             | No             | No             | No             |
| Menu and prices                   | Yes | Yes                             | View                         | View           | View           | View           | No             |
| Purchase and stock entry          | Yes | Yes                             | No                           | No             | Request        | Request        | Yes            |
| Expense entry                     | Yes | Yes                             | Petty cash only              | No             | No             | No             | No             |
| Cash in / cash out, day close     | Yes | Yes                             | Yes                          | No             | No             | No             | No             |
| Staff, attendance, salary         | Yes | Yes (no salary edit by default) | No                           | Own attendance | Own attendance | Own attendance | Own attendance |
| Reports                           | All | All operational                 | Own shift                    | No             | Kitchen only   | Bar only       | Stock only     |
| Balance sheet, P/L                | Yes | View                            | No                           | No             | No             | No             | No             |
| Users, roles, settings, audit log | Yes | No                              | No                           | No             | No             | No             | No             |

Every void, discount, refund, price change and bill edit is written to an **audit log** with user, time, old value and new value.

## 5. Functional requirements by module

There are 16 modules. Each requirement has an ID so it can be traced through development and testing.

### M1. Floor, table and cabin management

- FR-1.1 Live floor map grouped by zone, with tables and cabins shown in different shapes and colours by status (Section 3).
- FR-1.2 Open a seat with guest count and waiter; running order time is shown on the tile.
- FR-1.3 Merge tables, move an order to another seat, split a bill by item or by equal share.
- FR-1.4 Reservations: name, phone, seat, BS date and time, advance amount.
- FR-1.5 Optional cabin charge or minimum spend per cabin.

### M2. Order management (tablet / mobile)

- FR-2.1 Order screen built for tablets and phones (PWA, installable, large touch buttons).
- FR-2.2 Order types: dine-in (table / cabin), counter, takeaway, delivery.
- FR-2.3 Pick items by category with search; add quantity, notes ("less spicy") and modifiers (half/full, add-ons).
- FR-2.4 Send to kitchen creates a KOT. Food items route to the kitchen, drinks and cigarettes route to the bar.
- FR-2.5 Add more items to a running order; each new batch makes a new KOT.
- FR-2.6 Running tab: a customer can keep a tab open across the evening and settle once.
- FR-2.7 Cancel or void an item after KOT needs a reason and an Admin PIN.

### M3. Kitchen and bar (KOT / KDS)

- FR-3.1 Kitchen display screen with new, preparing and ready columns; order age timer turns red after a set number of minutes.
- FR-3.2 Optional KOT print on a kitchen thermal printer, and a separate one for the bar.
- FR-3.3 Ready status notifies the waiter's device.
- FR-3.4 Mark an item out of stock; it disappears from the order screen and the QR menu.

* FR-3.5 Full ticket status flow: New → Accepted → Preparing → Ready → Served, each change stored with time and user (order status history).
* FR-3.6 Multiple preparation stations set by Admin (e.g. Momo, Snacks, Chicken, Drinks/Bar); each menu item routes to its station's screen and printer.

### M4. POS and billing

- FR-4.1 Bill from a seat or a counter sale, with subtotal, discount (% or Rs), service charge (configurable, e.g. 10%), VAT 13% if registered, round-off, and grand total.
- FR-4.2 Payment modes: Cash, Fonepay QR, eSewa, Khalti, card, bank transfer, Credit (udharo), complimentary. Split payment across modes is allowed.
- FR-4.3 Print the bill on an 80 mm thermal printer (ESC/POS) with logo, PAN/VAT number, bill number, BS and AD date, seat, waiter, items and totals. Reprint is marked "COPY".
- FR-4.4 Bill numbers run in sequence per fiscal year (Shrawan to Asar) and cannot be deleted, only cancelled with a reason.
- FR-4.5 Quick counter mode for walk-in sales of cigarettes, water and drinks.
- FR-4.6 Day open with opening cash, and day close with cash count, expected vs actual and the difference.

* FR-4.7 Takeaway billing: choose Takeaway at the POS, enter customer name and phone (optional), add items, and take payment before or after preparation (setting). The bill gets a token number printed large for pickup, an optional packaging charge per item or per bill, and "TAKEAWAY" on the KOT so the kitchen packs it. A takeaway can also be billed to a credit customer.
* FR-4.8 Sales returns and refunds after payment need a reason and Admin authorization; they create a return record, reverse stock where the item was not made, and post to cash flow and accounts.
* FR-4.9 Each order item stores the actual selling price, discount and tax at the time of sale, so old invoices never change when menu prices change.

- FR-4.10 Dynamic payment QR per bill: when the cashier picks "QR payment", the system requests a Fonepay Dynamic QR with the exact bill amount and the bill number as reference (PRN). The QR shows on the POS screen or customer-facing screen and can print on the bill. When the guest pays, the payment is confirmed automatically (status check / notification), the bill is marked paid and the receipt prints. No amount typing, no screenshot checking. The QR expires after a few minutes and can be refreshed for the same bill.
- FR-4.11 eSewa static merchant QR stays as a fallback payment mode: the guest types the amount, and the cashier records the eSewa transaction ID; these payments are matched in bank and QR reconciliation (FR-9.6).
- FR-4.12 Online payment on the QR menu website (Phase 3 option) uses eSewa ePay v2 and Khalti web checkout.

Dynamic QR needs Fonepay Billing API credentials (merchant code, username, password, secret key) requested from WoodStone's bank for billing software / POS integration. eSewa's merchant QR is a fixed QR where the customer enters the amount; no public eSewa dynamic QR API for POS was found ([Fonepay Business](https://fonepay.com/business), [eSewa QR merchant](https://blog.esewa.com.np/?p=850)).

### M5. Menu and dynamic QR menu

- FR-5.1 Categories, items, price, photo, description, veg / non-veg tag, kitchen or bar station, availability and time-based availability.
- FR-5.2 One QR code per seat, generated in the system and printable as a table card. The QR opens woodstone website menu with the seat code, e.g. /menu?t=C2.
- FR-5.3 The website menu reads live from the system: prices, photos and out-of-stock changes appear instantly. No reprint is needed when the menu changes.
- FR-5.4 The QR token can be regenerated if a card is copied or misused.
- FR-5.5 Phase 2 option: guests place an order from the QR menu, and staff approve it before it goes to the kitchen. Calling a waiter from the QR menu is in Phase 1 (M16).

* FR-5.6 Every item has a code, category and subcategory, preparation station, per-item tax setting (VAT applicable or exempt), and shows its food cost and gross margin from the recipe.

### M6. Purchase and suppliers

- FR-6.1 Supplier list with phone, PAN and balance owed.
- FR-6.2 Purchase entry: BS date, supplier, items from the item master (no free text), quantity, unit, rate, total, paid now and paid by which mode.
- FR-6.3 Supplier payable ledger and supplier payments.
- FR-6.4 Each purchase updates stock and the average cost of the item.

* FR-6.5 Purchase orders: create a PO for a supplier (items, qty, expected rate, expected date), print or share it on WhatsApp, and track status Draft, Sent, Partly received, Received, Cancelled.
* FR-6.6 Receive goods against a PO (goods received note); differences in quantity or rate are flagged, and the received quantity becomes the purchase entry.

- FR-6.7 Purchase returns to a supplier reduce stock and the amount owed to that supplier.

### M7. Inventory and recipe costing

- FR-7.1 Item master with three kinds: raw ingredient (chicken, milk, gas), resale item (beer, cigarettes, soft drinks), and asset or capex (stone, blocks, furniture).
- FR-7.2 Units and conversions (kg to g, carton to pack to stick, crate to bottle).
- FR-7.3 Recipe for each menu item: ingredients and quantity per plate. Selling an item reduces ingredient stock automatically.
- FR-7.4 Food cost per plate is calculated from current average cost; profit per plate = price − cost.
- FR-7.5 Stock adjustment, wastage and staff meal entries with reason.
- FR-7.6 Low-stock alerts and a daily stock count for bar and cigarettes.

* FR-7.7 Opening stock entry on go-live, and stock issue from store to kitchen or bar.
* FR-7.8 Stock batches with batch cost and expiry date; expiry alerts and first-expiry-first-out issue.
* FR-7.9 Physical stock verification: count sheet, difference against system stock, and approved adjustment.

### M8. Expenses and operating cost

- FR-8.1 Expense categories set by Admin: Kitchen, Bar, Gas, Utilities (electricity, water / PANI supply), Rent, Salary, Repair, Marketing, Construction / Capex, Miscellaneous.
- FR-8.2 Expense entry: BS date, category, description, amount, paid by mode, receipt photo.
- FR-8.3 Recurring expenses (rent Rs 25,000 per month, electricity, water) are created each month as a reminder to confirm.
- FR-8.4 Each entry is tagged Operating expense or Investment / Capex, like the RENT sheet's status column today.
- FR-8.5 Filters by date range (daily, weekly, monthly, custom BS range), category, mode and user.

### M9. Cash flow, bank and wallets

- FR-9.1 Accounts: Cash in hand, each bank account, eSewa, Khalti, Fonepay settlement account.
- FR-9.2 Every sale payment, purchase payment, expense, salary, deposit and owner investment posts automatically to the right account.
- FR-9.3 Manual cash in and cash out entries with reason (owner withdrawal, bank deposit, float).
- FR-9.4 Transfer between accounts (cash deposited to bank, eSewa withdrawn to bank).
- FR-9.5 Daily cash book: opening, inflows, outflows, closing per account.

* FR-9.6 Bank and QR reconciliation: match Fonepay, eSewa, Khalti and bank settlements against recorded payments and flag differences.
* FR-9.7 Owner capital contributions, loans and owner withdrawals are recorded separately and never counted as sales or operating income; credit purchases and unpaid bills affect profit and payables/receivables, not cash, until paid.
* FR-9.8 Cash flow statement (operating, investing, financing) for any BS period.

### M10. Customer management, loyalty and credit (udharo)

- FR-10.1 Customer list with name and phone.
- FR-10.2 A bill paid by Credit adds to the customer balance; later payments reduce it.
- FR-10.3 Credit ledger and ageing (0–30, 31–60, 60+ days) with a total credit given and total received, matching the empty columns in the Sales sheet today.

* FR-10.4 Customer profile: name, phone, email, birthday, anniversary, preferred seat, notes; found by phone number at billing.
* FR-10.5 Visit history: number of visits, total spent, last visit, favourite items.
* FR-10.6 Loyalty points: earn points per Rs spent (rate set by Admin), redeem points as a discount on the bill, with expiry and a points ledger.
* FR-10.7 Customer tiers (e.g. Regular, Silver, Gold) by spend, and a list of birthdays this week for offers.

### M11. Kitchen profit / loss and item analysis

- FR-11.1 Kitchen P/L = food sales − ingredient cost used − kitchen-category expenses, by day and month.
- FR-11.2 Bar P/L the same way for drinks and cigarettes.
- FR-11.3 Item-wise report: quantity sold, revenue, cost, profit and margin %, sortable.
- FR-11.4 Most ordered items, least ordered items and peak hours.

### M12. HR and staff

- FR-12.1 Staff profile: name, phone, address, citizenship number (stored securely), role, joining date, salary type (monthly / daily) and amount.
- FR-12.2 Attendance: check-in and check-out by PIN on the counter tablet, with late and absent marks.
- FR-12.3 Shifts and weekly off.
- FR-12.4 Advances and deductions; monthly salary sheet with payslip print; salary payment posts to expenses and cash flow.
- FR-12.5 Leave requests and approval.
- FR-12.6 Staff performance: orders taken and sales value per waiter.

### M13. Accounting: P/L and balance sheet

- FR-13.1 Simple double-entry ledger created automatically from sales, purchases, expenses, salaries and transfers, so the owner never posts journals by hand.
- FR-13.2 Profit and loss for any BS period.
- FR-13.3 Balance sheet: assets (cash, bank, wallets, stock value, fixed assets / capex, customer credit) vs liabilities (supplier payables, salary payable) and owner equity (investment, retained profit, withdrawals).
- FR-13.4 Manual journal entry for Super Admin only, for corrections.

### M14. Settings and administration

- FR-14.1 Restaurant profile: name, logo, PAN/VAT number, address, phone, bill footer.
- FR-14.2 Tax settings: VAT on/off, service charge %, and whether prices include tax.
- FR-14.3 Printers: which printer gets bills, kitchen KOT and bar KOT.
- FR-14.4 Fiscal year, BS calendar, language (English / Nepali).
- FR-14.5 Users, roles, PIN reset, device list and backup / restore.

### M15. Accounts payable and accounts receivable

**Accounts payable (money WoodStone owes)**

- FR-15.1 Every credit purchase creates a payable against the supplier with bill number, BS date, amount and due date.
- FR-15.2 Pay a supplier fully or partly, from cash, bank or wallet; one payment can settle several bills.
- FR-15.3 Other payables: rent due, utility bills, salary payable and staff advances owed back.
- FR-15.4 Payables ageing (not due, 0–30, 31–60, 61–90, 90+ days), supplier statement, and an upcoming-dues list for the next 7 days.

**Accounts receivable (money owed to WoodStone)**

- FR-15.5 Every bill paid by Credit creates a receivable against the customer (dine-in or takeaway), with due date and credit limit per customer.
- FR-15.6 Receive payment fully or partly; one receipt can settle several bills; a receipt prints on the thermal printer.
- FR-15.7 Other receivables: staff advances, card/QR settlements not yet received from the bank or wallet.
- FR-15.8 Receivables ageing, customer statement (print or share on WhatsApp), and a warning at billing when a customer is over their credit limit.

**Accounting link**

- FR-15.9 Payables and receivables post automatically to the ledger and appear on the balance sheet as liabilities and assets.
- FR-15.10 Opening balances for existing suppliers and credit customers are entered on go-live.

### M16. Guest call and service notifications (QR)

A guest scans the table or cabin QR, taps a button, and the waiter for that seat and the reception counter hear a ring within 2 seconds. Unanswered calls escalate.

**Guest side (QR menu page)**

- FR-16.1 The QR page shows the seat name (e.g. "Cabin 2") and buttons set by Admin: Call waiter, Request bill, Water, Order help, and an optional note.
- FR-16.2 Optional "Call reception" button that opens the phone dialler (tel: link) to the restaurant number, for guests who prefer a voice call.
- FR-16.3 After tapping, the page shows live status: Sent → Waiter coming (with waiter's first name) → Done.
- FR-16.4 Abuse control: one request per seat per 60 seconds, calls only accepted for an open (occupied) seat unless Admin allows otherwise, QR token can be regenerated, and every request is logged with time and device.

**Staff side (ringing)**

- FR-16.5 Routing: the request goes to the waiter assigned to that seat's open order; if none, to all waiters on shift in that zone. The reception/cashier screen always receives it.
- FR-16.6 Ring: a looping sound, vibration (Android), and a full-screen banner "Cabin 2 · Call waiter · 0:15" with Accept and Done buttons. Different sounds per request type (bill request sounds different from water).
- FR-16.7 Accept stops the ring on all other devices and shows the guest "Waiter coming". Done closes the request.
- FR-16.8 Escalation: not accepted in 60 s → ring again on every waiter and reception; not accepted in 3 min → notify Admin/manager. Times are configurable.
- FR-16.9 Reception panel lists all open calls with seat, type and age, oldest first, so the counter can send someone.
- FR-16.10 Waiters can also be pinged from the POS ("Food ready for C2"), using the same ring.
- FR-16.11 Report: number of calls per seat and average response time per waiter.

**How it is implemented**

| Part                               | Technology                                                                                     | Detail                                                                                                                                                                                 |
| ---------------------------------- | ---------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| QR link                            | Seat qr\_token                                                                                 | /menu?t=\<token> resolves to the seat; no guest login needed                                                                                                                           |
| Create request                     | REST API                                                                                       | POST /api/v1/public/seats/{token}/service-requests with type and note; rate-limited; saves a service\_requests row (seat, type, status, created, accepted\_by, accepted\_at, done\_at) |
| Instant ring, app open             | Laravel Reverb websockets                                                                      | Event broadcast to channels outlet.1.reception and staff.{waiter\_id}; the waiter PWA plays the sound with Web Audio and vibrates                                                      |
| Ring when screen is locked         | Web Push (VAPID) via service worker                                                            | Works on Android Chrome; on iPhone only if the PWA is added to the home screen (iOS 16.4+); uses the system notification sound, cannot loop                                            |
| Reliable loud ring on staff phones | Small Android app (Capacitor wrapper of the same PWA) + Firebase Cloud Messaging high-priority | Custom alarm-style notification channel with looping sound, works when phone is locked or app is closed; recommended for Phase 2                                                       |
| Counter / reception                | Always-on screen with speakers                                                                 | Screen Wake Lock keeps it on; staff tap "Start shift" once so the browser allows sound                                                                                                 |
| Guest status                       | Polling every 5 s or Server-Sent Events                                                        | Guest page shows Sent / Waiter coming / Done                                                                                                                                           |
| Escalation                         | Laravel scheduled job / delayed queue job                                                      | Checks unaccepted requests at 60 s and 3 min and re-broadcasts                                                                                                                         |
| Internet loss                      | Local server on counter PC                                                                     | Guests on restaurant Wi-Fi reach the local server, so calls keep ringing inside the restaurant; calls from mobile data need internet                                                   |

**Settings:** which buttons show, cooldown seconds, escalation times, sound per type, quiet hours, reception phone number for the tel: button.

**New tables:** service\_requests, push\_subscriptions (Web Push), device\_tokens (FCM).

## 6. Reports

Every report filters by BS date range (today, yesterday, this week, this month, custom) and exports to PDF and Excel. The owner dashboard shows today's key figures on one phone screen.

| Report                      | Shows                                                                                                    | Filters                       | Who sees it                             |
| --------------------------- | -------------------------------------------------------------------------------------------------------- | ----------------------------- | --------------------------------------- |
| Owner dashboard             | Today's sales, bills, average bill, cash in hand, expenses, profit estimate, top 5 items, running tables | Today / this month            | Super Admin, Admin                      |
| Daily sales                 | Sales per day, by payment mode, discount, service charge, VAT, cancellations                             | Date range                    | Super Admin, Admin, Cashier (own shift) |
| Revenue day / month         | Revenue trend by day and by Nepali month, month vs last month                                            | Date range, zone              | Super Admin, Admin                      |
| Most ordered items          | Top and bottom items by quantity and value                                                               | Date range, category, station | Super Admin, Admin, Kitchen             |
| Item-wise cost and profit   | Qty, revenue, cost, profit, margin % per item                                                            | Date range, category          | Super Admin, Admin                      |
| Table / cabin performance   | Revenue, covers and average bill per seat and per zone                                                   | Date range                    | Super Admin, Admin                      |
| Kitchen P/L                 | Food sales vs ingredient cost and kitchen expenses                                                       | Day / month                   | Super Admin, Admin                      |
| Bar P/L                     | Drinks and cigarette sales vs cost                                                                       | Day / month                   | Super Admin, Admin                      |
| Expenses                    | By category, operating vs investment, by mode                                                            | Date range, category          | Super Admin, Admin                      |
| Operational cost            | Rent, utilities, salary, gas, repair per month                                                           | Month                         | Super Admin, Admin                      |
| Sales vs purchase           | Sales against purchases per day and month                                                                | Date range                    | Super Admin, Admin                      |
| Cash flow / cash book       | Opening, cash in, cash out, closing per account                                                          | Date range, account           | Super Admin, Admin                      |
| Day close                   | Expected vs counted cash, difference, by cashier                                                         | Date                          | Super Admin, Admin                      |
| Stock                       | Current stock, value, low stock, wastage, consumption                                                    | Date, item kind               | Super Admin, Admin, Store               |
| Customer credit             | Balance and ageing per customer                                                                          | As of date                    | Super Admin, Admin                      |
| Supplier payable            | Balance per supplier                                                                                     | As of date                    | Super Admin, Admin                      |
| Staff                       | Attendance, salary sheet, sales per waiter                                                               | Month                         | Super Admin, Admin                      |
| Void / discount / audit     | Every void, discount and edit with user and reason                                                       | Date range, user              | Super Admin                             |
| Profit and loss             | Income, cost of goods, expenses, net profit                                                              | BS period                     | Super Admin                             |
| Balance sheet               | Assets, liabilities, equity                                                                              | As of date                    | Super Admin                             |
| VAT sales and purchase book | IRD-format sales and purchase registers (if VAT registered)                                              | Month                         | Super Admin                             |

**Also in reports:** accounts payable and receivable ageing (M15), cash flow statement, hourly sales, category-wise revenue, slow-moving items, food cost %, wastage %, staff-wise sales, purchase trend, monthly profit comparison, takeaway vs dine-in vs cabin sales, and sales and purchase returns. Date filters include quarter.

## 7. Data model

About 35 tables in 8 groups. Every money and stock table stores both a BS date and an AD date, plus created\_by and timestamps. Paid bills and posted ledger rows are never hard-deleted.

| Group      | Main entities                                                                                                                                                                                                                                       |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Access     | users, roles, permissions, role\_permissions, devices, audit\_logs                                                                                                                                                                                  |
| Floor      | zones, seats (type: table / cabin / counter / virtual, qr\_token), reservations                                                                                                                                                                     |
| Menu       | menu\_categories, menu\_items, item\_modifiers, recipes (menu\_item → stock\_item, qty per plate)                                                                                                                                                   |
| Orders     | orders (seat, type, waiter, status), order\_items (qty, price, notes, status), kots, kot\_items                                                                                                                                                     |
| Billing    | bills (number, fiscal year, subtotal, discount, service, VAT, total, status), bill\_payments (mode, amount), customers, customer\_ledger, loyalty\_transactions                                                                                     |
| Stock      | stock\_items (kind: ingredient / resale / asset), units, unit\_conversions, suppliers, purchase\_orders, purchase\_order\_items, purchases, purchase\_items, supplier\_ledger, stock\_movements (in / out / wastage / adjust / sale), stock\_counts |
| Money      | accounts (cash, bank, wallet), transactions (account, in / out, source type and id), transfers, expense\_categories, expenses, recurring\_expenses, day\_closes                                                                                     |
| Accounting | ledger\_accounts (chart of accounts), journal\_entries, journal\_lines (auto-posted from every source)                                                                                                                                              |
| HR         | staff (links to user), shifts, attendance, leaves, salary\_structures, advances, payrolls, payroll\_lines                                                                                                                                           |

**Additional tables:** order\_status\_history, payment\_allocations (split payments), sales\_returns, purchase\_returns, stock\_batches, stock\_adjustments, payables, receivables, payment\_settlements (one payment against many bills), number\_sequences (bill, token, PO numbers), printer\_settings, business\_settings and sync\_queue (offline device transactions).

### 7.1 ER diagram

&#91;embedded content: ER diagram · 27 core tables in 7 groups\]

The order chain runs seat → order → order items → bill → payments. Recipes tie menu items to stock items, so each sale deducts ingredients. Supporting tables (status history, returns, batches, sequences, settings, sync queue) are listed above and left out of the drawing to keep it readable.

**Key rule:** stock, cash and ledger balances are never typed in. They are always the sum of movements, so every number can be traced back to the bill, purchase or expense that caused it.

## 8. Nepal-specific, technical and hardware requirements

The system must run in Nepali conditions: BS dates, Nepali payment wallets, IRD billing rules and unreliable internet or power.

### 8.1 Nepal-specific

- Bikram Sambat calendar everywhere (date picker, reports, Nepali month names), with AD stored alongside. Fiscal year Shrawan 1 to Asar end.
- NPR with Nepali number grouping (1,00,000) as an option.
- Payment modes Fonepay, eSewa and Khalti, with the transaction ID recorded. Gateway integration is phase 2; phase 1 records the mode and reference.
- If WoodStone is VAT registered: tax invoice format, 13% VAT, and IRD sales and purchase books. No IRD CBMS real-time bill sync is required (confirmed by client).
- English and Nepali interface labels; menu items can have a Nepali name.

### 8.2 Non-functional

| Area           | Requirement                                                                                               |
| -------------- | --------------------------------------------------------------------------------------------------------- |
| Devices        | Works on Android tablets, phones, iPad and desktop browser; installable PWA                               |
| Speed          | Order screen opens in under 2 s; KOT reaches the kitchen screen in under 2 s on local network             |
| Offline        | Order taking and billing keep working on the local network if internet drops; data syncs when back        |
| Real time      | Floor map, KDS and waiter notifications update live (websockets)                                          |
| Security       | Hashed passwords and PINs, role checks on every API call, HTTPS, auto-logout on shared tablets, audit log |
| Backup         | Daily automatic database backup off the server, kept 30 days; one-click restore by Super Admin            |
| Data integrity | Bills and ledger rows cannot be deleted, only cancelled or reversed with reason                           |
| Scale          | One outlet now; data model allows more outlets later (outlet\_id on main tables)                          |

### 8.3 Hardware

| Item                                        | Qty (proposed) | Purpose                                            |
| ------------------------------------------- | -------------- | -------------------------------------------------- |
| 80 mm thermal printer (ESC/POS, LAN or USB) | 1              | Bills at the counter                               |
| 80 mm thermal printer                       | 1–2            | Kitchen KOT and bar KOT                            |
| Counter PC or tablet with cash drawer       | 1              | POS, day close                                     |
| Android tablets or staff phones             | 2–4            | Waiter ordering                                    |
| Kitchen screen (TV or tablet)               | 1              | KDS                                                |
| Wi-Fi router with good garden coverage      | 1–2            | Outdoor tables and cabins are far from the counter |
| Printed QR table cards                      | One per seat   | QR menu                                            |

Printing from a browser to thermal printers needs a small local print bridge (e.g. QZ Tray or a tiny local print service) on the counter PC.

## 9. Migration, phases and open questions

Old sheet data is imported as history, and the system goes live in 3 phases so the restaurant can start using POS early.

### 9.1 Data migration from Google Sheets

1. Sales tab: import each day's total as a historical daily sales row (no item detail), so revenue reports start from 2082.
2. Kharcha tab: fill the blank dates down, clean typos, map each heading to a category and stock item using a mapping table (e.g. Shikher, Surya → Resale: Cigarettes; Stone, Blck → Capex: Construction). Import as historical expenses.
3. RENT tab: import as recurring rent of Rs 25,000 with the investment / expense status kept.
4. bank deposit, Investment, esewa karobar: import as opening balances or transactions once their fields are confirmed.
5. Opening stock count and opening cash on go-live day.

### 9.2 Phases

| Phase              | Modules                                                                                                                                                     | Outcome                                     |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| 1. Core POS        | Auth and roles, floor and seats, menu, orders, KOT/KDS, billing, thermal print, payments, day close, QR menu (view only), daily sales and top items reports | Restaurant stops typing daily sales by hand |
| 2. Money and stock | Purchase, suppliers, inventory, recipes, expenses, cash flow, bank and wallets, customer credit, kitchen/bar P/L, item profit, migration of old sheets      | Real profit and cash position               |
| 3. HR and accounts | Staff, attendance, payroll, auto ledger, P/L, balance sheet, IRD books, QR self-ordering, payment gateways                                                  | Full back office                            |

### 9.3 Open questions for WoodStone

- [ ] Exact count of tables and cabins per zone, and seat capacity?
- [ ] Is WoodStone VAT registered or PAN only? Is a 10% service charge applied?
- [ ] Do cabins have a minimum spend or a cabin charge?
- [ ] Are prices on the menu VAT inclusive?
- [ ] How many staff, and in which roles? Monthly or daily wage?
- [ ] Is there a separate bar person, or does the cashier serve drinks?
- [ ] Are hookah and cigarettes sold by stick and by pack?
- [ ] Which payment modes are used today (Fonepay, eSewa, Khalti, card)?
- [ ] Who are the credit (udharo) customers and what are their current balances?
- [ ] What fields are in the bank deposit, Investment and esewa karobar tabs?
- [ ] Which website will host the QR menu (domain), and is it on NepalHost?
- [ ] Existing printers and tablets, or buy new?
- [ ] Should the system be cloud-hosted, on a local server at the restaurant, or both?

## 9A. Development standards and recommendations

The project follows the ISO/IEC/IEEE 29148 requirements structure, OWASP security standards and current Laravel conventions. The items below are mandatory for the build unless Softechpark signs off an exception.

### 9A.1 Requirements standard (ISO/IEC/IEEE 29148)

| 29148 element                           | Where it is in this document | Status            |
| --------------------------------------- | ---------------------------- | ----------------- |
| Purpose and scope                       | Section 1                    | Done              |
| Current system / context                | Section 2                    | Done              |
| Stakeholders and user classes           | Section 4                    | Done              |
| Functional requirements with unique IDs | Section 5 (FR-x.y)           | Done              |
| Data requirements                       | Section 7                    | Done              |
| Non-functional requirements             | Section 8.2                  | Done              |
| Constraints (legal, hardware, local)    | Sections 8.1, 8.3            | Done              |
| Priority per requirement                | 9A.2 (MoSCoW)                | Done              |
| Acceptance criteria                     | 9A.3                         | Done              |
| Open issues                             | Section 9.3                  | Waiting on client |

### 9A.2 Priorities (MoSCoW)

| Module                             | Priority             | Phase |
| ---------------------------------- | -------------------- | ----- |
| M1 Floor, tables, cabins           | Must                 | 1     |
| M2 Orders (tablet/mobile)          | Must                 | 1     |
| M3 Kitchen and bar KOT/KDS         | Must                 | 1     |
| M4 POS billing and thermal print   | Must                 | 1     |
| M5 Menu + QR menu (view)           | Must                 | 1     |
| M14 Settings, users, roles         | Must                 | 1     |
| M6 Purchase and purchase orders    | Must                 | 2     |
| M7 Inventory and recipes           | Must                 | 2     |
| M8 Expenses and operating cost     | Must                 | 2     |
| M9 Cash flow, bank, wallets        | Must                 | 2     |
| M10 Customers, loyalty, credit     | Should               | 2     |
| M11 Kitchen/bar P/L, item profit   | Should               | 2     |
| M12 HR and payroll                 | Should               | 3     |
| M13 Ledger, P/L, balance sheet     | Should               | 3     |
| QR self-ordering, payment gateways | Could                | 3     |
| Multi-outlet, mobile native apps   | Won't (this release) | Later |

### 9A.3 Acceptance criteria (definition of done per module)

1. Every FR in the module works on a tablet and a desktop browser with seeded demo data.
2. Role checks match the permission matrix in Section 4.1, verified with one test per role group.
3. Totals reconcile: a day of test orders gives the same figure in the bill list, daily sales report, cash book and ledger.
4. Bills print correctly on an 80 mm thermal printer, with BS and AD dates.
5. No critical or high findings from static analysis and the OWASP checklist.
6. User guide page and API docs updated for the module.

**Pre-launch tests:** 100 simultaneous order-line operations, bill cancellation and refund, every recipe's stock deduction, daily cash reconciliation, printer failure, internet loss and recovery, role permissions, backup restore on a separate environment, and financial reports checked against sample accounting records.

### 9A.4 Engineering standards

Each area below names the exact choice the developers must use.

#### 1. Architecture

- **Pattern:** modular monolith on Laravel 11 (PHP 8.3). One codebase, one database, split into domain modules. Microservices are not used: one outlet and a small team do not need them.
- **Modules:** app/Modules/{Floor, Orders, Kitchen, Billing, Menu, Inventory, Purchase, Expenses, CashFlow, Customers, HR, Accounting, Reports, Settings}. Each module holds its own Models, Actions, Services, Http (Controllers, Requests, Resources), Policies, Events, Listeners and migrations.
- **Request flow:** Route → Controller (thin) → FormRequest (validation) → Policy (permission) → Action / Service (business logic, DB transaction) → Eloquent Model → API Resource (response).
- **Cross-module rules:** modules talk through events, not direct calls. Example: BillPaid → listeners deduct recipe stock, post ledger entries, add loyalty points and update the cash book (queued jobs on Redis).
- **Real time:** Laravel Reverb websockets. Private channels per outlet and station, e.g. outlet.1.kds.kitchen, outlet.1.floor.
- **Frontends:** (a) POS, waiter and KDS screens: Vue 3 + TypeScript + Inertia + Pinia, installable PWA; (b) back office: Filament 3 (Livewire 3 + Tailwind); (c) public QR menu: lightweight Blade page plus a JSON API the website can embed.
- **Offline:** PWA service worker (Workbox) caches the menu and seats; orders made offline wait in an IndexedDB queue and sync with an idempotency key when the connection returns.
- **Printing:** a local print bridge on the counter PC (QZ Tray or a small Node ESC/POS service) receives print jobs from the browser.

#### 2. Code style

| Layer                    | Standard                                    | Tooling and rules                                                                                                                                                                                               |
| ------------------------ | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Backend (PHP)            | PSR-12 with Laravel preset                  | Laravel Pint; declare(strict\_types=1); typed properties and return types; PHP enums for statuses (OrderStatus, BillStatus); readonly DTOs between layers; one Action class per use case with a handle() method |
| Naming (backend)         | Laravel conventions                         | Classes StudlyCase, methods camelCase, DB tables plural snake\_case, columns snake\_case, routes kebab-case                                                                                                     |
| Frontend (Vue)           | Vue 3 official style guide (priority A + B) | Composition API with script setup lang="ts"; components PascalCase; composables useXxx; Pinia stores per domain; no any types                                                                                   |
| Lint / format (frontend) | ESLint + Prettier                           | eslint-plugin-vue (recommended), @typescript-eslint, prettier-plugin-tailwindcss; 2-space indent, single quotes, semicolons on                                                                                  |
| CSS                      | Tailwind CSS 3                              | Design tokens in tailwind.config; no inline styles; dark mode for KDS                                                                                                                                           |
| Language                 | i18n from day one                           | Laravel lang files and vue-i18n, English and Nepali                                                                                                                                                             |
| Commits                  | Conventional Commits                        | feat(billing): split payment FR-4.2                                                                                                                                                                             |

#### 3. Static analysis

- PHP: Larastan (PHPStan) at level 6 from the start, raised to level 8 before v1.0. Baseline file is not allowed for new code.
- Rector for safe refactors and PHP/Laravel upgrades.
- Frontend: vue-tsc type check and ESLint with zero warnings in CI.
- Dependencies: composer audit and npm audit on every build; fail on high or critical.
- Optional: SonarCloud quality gate (no new bugs, vulnerabilities or duplicated blocks over 3%).

#### 4. API

- REST over JSON, versioned under /api/v1. Plural nouns (/orders, /bills/{uuid}/payments), standard HTTP verbs and status codes.
- Auth: Sanctum cookie auth for the PWA and back office; Sanctum device tokens for tablets and the website menu (read-only scope).
- Errors follow RFC 9457 problem details: type, title, status, detail, errors per field.
- Lists use page pagination with meta (total, per\_page), filters as ?filter\[status\]=open and sorting as ?sort=-created\_at.
- Idempotency-Key header is required on POST orders, bills and payments, so offline retries never double-charge.
- Rate limits: 60 requests/min per user, 5 attempts/min on PIN login.
- Documentation: OpenAPI 3.1 generated automatically by Scramble, published at /docs/api for staff only.
- Public QR menu API is read-only and returns only menu data, never prices history or stock levels.

#### 5. Database

- PostgreSQL 16 (recommended; already used on Softechpark servers) or MySQL 8 with utf8mb4 for Nepali text.
- Tables plural snake\_case; bigint primary keys inside, UUID public IDs in URLs and APIs.
- Money as DECIMAL(12,2), quantities as DECIMAL(12,3); never float.
- Timestamps stored in UTC (timestamptz), shown in Asia/Kathmandu. BS date stored as text YYYY-MM-DD next to the AD date column.
- Foreign keys everywhere with ON DELETE RESTRICT; indexes on dates, status, seat\_id, outlet\_id.
- Bills, payments, stock movements and journal lines are append-only (no update or delete; corrections are reversal rows). Soft deletes only on master data (menu items, suppliers, staff).
- Billing runs inside one DB transaction; bill number comes from a locked per-fiscal-year sequence so numbers never skip or repeat.
- All schema changes through reversible migrations; seeders for roles, permissions, demo floor and menu.
- No raw SQL without bound parameters.

#### 6. Security (OWASP Top 10 2021 + ASVS 4.0 Level 2)

| OWASP risk                    | Control in WoodStone                                                                                                                        |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| A01 Broken access control     | Policy on every route, deny by default, outlet scoping, permission matrix tests                                                             |
| A02 Cryptographic failures    | HTTPS only (HSTS), argon2id for passwords and PINs, encrypted casts for citizenship number and phone of staff                               |
| A03 Injection                 | Eloquent / bound queries, FormRequest validation, Vue and Blade auto-escaping                                                               |
| A04 Insecure design           | Approval PIN for voids and discounts, append-only money tables, audit log                                                                   |
| A05 Security misconfiguration | APP\_DEBUG off in production, security headers (CSP, X-Frame-Options, X-Content-Type-Options), no default credentials                       |
| A06 Vulnerable components     | composer/npm audit in CI, monthly dependency updates                                                                                        |
| A07 Auth failures             | 6-digit PIN with lockout after 5 tries, TOTP 2FA for Super Admin, 15-minute idle logout on shared tablets, secure HttpOnly SameSite cookies |
| A08 Integrity failures        | Signed Docker images from CI only, idempotency keys, locked dependency files                                                                |
| A09 Logging failures          | Audit log for money actions and logins, alerts on repeated failed logins                                                                    |
| A10 SSRF                      | No user-supplied URLs fetched by the server                                                                                                 |

Also: receipt photo uploads checked for type and size and stored on a private disk; QR tokens are random 32 characters, never sequential; secrets only in .env or a vault, never in git; OWASP ZAP baseline scan on staging before every release.

#### 7. Nepal Individual Privacy Act, 2075 (2018)

- Collect personal data only for a stated purpose: customer name and phone for billing, credit and loyalty; staff details for HR and payroll.
- Marketing messages (birthday offers) only to customers who opt in; the opt-in is stored with date.
- Staff citizenship number and salary visible to Super Admin only; encrypted at rest.
- A customer or staff member can ask to see or correct their data; Admin can export or anonymise a customer record.
- No sharing of personal data with third parties except payment providers needed to complete a payment.
- Retention: inactive customer marketing data removed after 2 years or on request; financial records kept as tax law requires.
- The Electronic Transactions Act, 2063 also applies to electronic records. Final retention periods should be confirmed with WoodStone's lawyer or accountant.

#### 8. IRD e-billing (Inland Revenue Department, Nepal)

- Tax invoice shows seller name, address and PAN/VAT number, invoice number, BS date, buyer name and PAN (when given or required), items, quantity, rate, discount, taxable amount, 13% VAT and total.
- Invoice numbers are sequential per fiscal year and can never be deleted or edited; a mistake is fixed with a cancellation and credit note.
- Reprints are marked "Copy of original" with print count; printed time and printed-by are stored.
- Monthly sales book and purchase book in IRD format.
- A billing audit view with IRD's required columns (fiscal year, bill no, customer name and PAN, bill date, amount, discount, taxable amount, tax amount, total, printed, active, printed time, entered by, printed by, payment method).
- No real-time bill sync to IRD CBMS: not required for WoodStone (confirmed by client), so it is out of scope.
- Billing software used for VAT invoices may need IRD approval/registration. The latest IRD e-billing procedure, thresholds and approval steps must be checked with IRD or WoodStone's accountant before go-live.

#### 9. Accessibility of the QR menu (WCAG 2.1 AA)

- Text contrast at least 4.5:1, text readable when zoomed to 200%.
- Tap targets at least 44 × 44 px; works one-handed on small phones.
- Food photos have alt text; semantic headings per category; lang attribute set for English and Nepali.
- Page under 500 KB on first load, WebP images lazy-loaded; Lighthouse scores: accessibility 90+, performance 85+ on a 4G phone.

#### 10. Light testing

- Pest 3 for the backend: one happy-path feature test per module.
- Unit tests only for risky maths: VAT, service charge, discounts, rounding, split payment, recipe stock deduction, BS↔AD conversion, bill numbering.
- Vitest for the order cart and bill total composables.
- One Playwright smoke test: login → open table → order → KOT → bill → pay → print.
- Whole suite runs in under 2 minutes. No coverage target; tests cover money and stock first.

#### 11. Git workflow

- Private GitHub repository. Trunk-based: main is protected and always deployable.
- Short feature branches (feature/billing-split-payment), merged by pull request with 1 review and green CI; squash merge.
- Conventional Commits with the FR ID; CHANGELOG generated by release-please.
- Releases tagged with SemVer (v1.0.0 = Phase 1 live). Hotfixes branch from the release tag and merge back to main.

#### 12. Automated checks (CI/CD on GitHub Actions)

1. On every pull request: composer install, Pint --test, Larastan, Pest (with a PostgreSQL service), npm ci, ESLint, vue-tsc, Vitest, Vite build, composer audit, npm audit.
2. On merge to main: build Docker image, push to GitHub Container Registry, deploy to staging automatically, run the Playwright smoke test against staging.
3. On a version tag: deploy to production after a manual approval, run migrations, restart queue and Reverb workers.
4. Pre-commit hooks (lint-staged) run Pint and Prettier locally so CI rarely fails on style.

#### 13. Environments

| Environment     | Where                                                                                            | Data                          | Notes                                           |
| --------------- | ------------------------------------------------------------------------------------------------ | ----------------------------- | ----------------------------------------------- |
| Local           | Developer Mac, Docker Compose (nginx, php-fpm, PostgreSQL, Redis, Reverb, queue worker, Mailpit) | Seeded demo data              | APP\_DEBUG on, Telescope on                     |
| Staging         | Cloud VPS, staging subdomain                                                                     | Anonymised copy of production | Same Docker image as production; client testing |
| Production      | Cloud VPS (e.g. NepalHost), Docker, Nginx, Let's Encrypt TLS                                     | Live                          | APP\_DEBUG off, Telescope off                   |
| Restaurant edge | Counter PC on restaurant Wi-Fi                                                                   | Print jobs, offline queue     | Print bridge, UPS for router and printer        |

#### 14. Monitoring

- Sentry for backend and Vue errors, with release tracking.
- Laravel Horizon for queues and Laravel Pulse for slow requests and queries.
- Uptime check every minute on /up and the QR menu page (Uptime Kuma or similar).
- Daily log rotation kept 14 days; alerts by email and Telegram for downtime, failed jobs, failed backups and repeated failed logins.
- Admin dashboard shows live status of the websocket server, print bridge and each kitchen/bar printer.

#### 15. Backups (3-2-1 rule)

- spatie/laravel-backup runs daily at 02:00 Nepal time: database dump plus uploaded files, encrypted.
- Three copies on two kinds of storage, one off-site: production server, a second server, and S3-compatible cloud storage.
- Retention: 7 daily, 4 weekly, 12 monthly.
- Restore tested once a month on staging, with the result logged.
- Super Admin can download the latest backup; a failed backup sends an alert.

#### 16. Documentation

- README.md: setup in under 15 minutes with Docker.
- CONTRIBUTING.md: these standards, branch and commit rules.
- docs/adr/: architecture decision records (one page per major decision).
- ER diagram generated from the database; OpenAPI docs at /docs/api.
- Runbooks: deployment, rollback, backup restore, printer setup.
- User manuals per role (Owner, Manager, Cashier, Waiter, Kitchen, Bar, Store) in English and Nepali, with screenshots.
- CHANGELOG.md updated on every release.

### 9A.5 Recommendations

- Host on a cloud VPS (or NepalHost) with a small local fallback for order taking and printing when the internet drops.
- Use a local print bridge on the counter PC for ESC/POS printing; do not depend on browser print dialogs.
- Load-test the KDS and floor map with 10 devices on the restaurant Wi-Fi before go-live.
- Run Phase 1 in parallel with the Google Sheet for one week, comparing daily totals, before switching off the sheet.
- Train staff by role with a one-page guide for each screen.

**Offline decision for v1:** one main counter PC is the primary local terminal (local server + local database). Tablets, kitchen screens and printers connect to it over the restaurant LAN, so billing never stops without internet. Cloud sync to the central database and owner dashboard is an extra layer, using unique transaction IDs, conflict checks and retry, and unsynced transactions are shown clearly.

## 10. Master build prompt

Paste this prompt into Claude Code once the open questions are answered. It builds Phase 1 first and stops for review. The stack is a suggestion; change it if Softechpark prefers another.

```markdown
You are building "WoodStone RMS", a restaurant management system for WoodStone, a garden restaurant and bar in Nepal, for Softechpark Pvt. Ltd.
Read the requirements document (Sections 1-9) before writing code. Follow its requirement IDs (FR-x.y) and name them in commit messages.

STACK

- Laravel 11 (PHP 8.3), PostgreSQL 16 (recommended) or MySQL 8, Redis, Laravel Reverb for websockets, Sanctum auth.
- Admin/back office: Filament 3 (Livewire 3 + Tailwind).
- Waiter, POS and KDS screens: Vue 3 + TypeScript + Inertia + Pinia as an installable PWA, touch-first, works on tablets and phones.
- Docker Compose for local and production. Daily DB backup job.

GLOBAL RULES

- Bikram Sambat dates in the UI and reports (use a BS<->AD converter library); store both bs_date and ad_date. Fiscal year Shrawan-Asar.
- Currency NPR, 2 decimals, optional Nepali grouping.
- Three login levels: Super Admin, Admin, Staff. Staff roles seeded: Cashier, Waiter, Kitchen, Bartender, Store keeper. Use spatie/laravel-permission; seed the permission matrix from Section 4.1. Staff log in with username + PIN on shared devices, auto-logout after idle.
- Never hard-delete bills, payments, stock movements or ledger rows; cancel/reverse with reason. Write every void, discount, refund, price change and bill edit to audit_logs.
- Balances (stock, cash, credit, payables) are always computed from movements.
- Add outlet_id to main tables for future multi-outlet support.
- Seed demo data: zones Garden (G1-G8 tables), Cabin row (C1-C3 cabins), Indoor (H1-H4 tables, C4 cabin), BAR counter, TA takeaway; a sample menu (momo, chowmein, chicken chilli, french fries, wings, sukuti, popcorn, tea, coke, sprite, beer, cigarettes).

PHASE 1 - CORE POS (build now)

1. Auth, roles, permissions, PIN login, audit log.
2. Floor: zones, seats (table/cabin/counter/virtual), live floor map with status colours, merge, move, split, reservations.
3. Menu: categories, items, modifiers, station (kitchen/bar), availability, photos.
4. Orders: dine-in/counter/takeaway, add items with notes, running tab, send KOT; food->kitchen, drinks/cigarettes->bar; void after KOT needs reason + Admin PIN.
5. KDS: kitchen and bar screens (new / preparing / ready) via Reverb; ready notifies waiter.
6. Billing (dine-in, cabin, counter and takeaway; takeaway gets a token number, optional packaging charge and TAKEAWAY on the KOT): discount, service charge %, VAT 13% toggle, round-off, split payment (Cash, Fonepay Dynamic QR, eSewa static QR, Khalti, Card, Bank, Credit, Complimentary); Fonepay Dynamic QR per bill via the bank's Billing API with bill number as PRN, shown on screen, auto-confirmed by status polling, then bill marked paid and receipt printed; credentials only from .env, bill numbers per fiscal year, reprint marked COPY.
7. Thermal printing: 80mm ESC/POS bill, kitchen KOT and bar KOT via a local print bridge (QZ Tray or a small Node print service on the counter PC). Bill shows logo, PAN/VAT, BS+AD date, seat, waiter, items, totals.
8. Day open / day close with cash count and difference.
9. Dynamic QR menu + guest call (M16): one qr_token per seat; Call waiter / Request bill / Water buttons (rate-limited, open seats only) create a service request that rings the seat's waiter and the reception screen via Reverb (looping Web Audio sound, vibration, full-screen banner with Accept/Done), escalates at 60 s and 3 min, sends Web Push when the screen is locked, and shows the guest live status; optional tel: Call reception button; printable QR cards; public read-only menu page /menu?t=<token> (and a JSON API the WoodStone website can embed); live prices and out-of-stock.
10. Reports: owner dashboard, daily sales by payment mode, revenue by day and Nepali month, most ordered items. PDF + Excel export.

PHASE 2 (after review): purchase orders with goods receiving, purchase and suppliers, stock items (ingredient/resale/asset), units and conversions, recipes and auto stock deduction, food cost per plate, expenses with categories and operating/investment tag, recurring rent/utilities, cash flow accounts (cash, bank, eSewa, Khalti, Fonepay) with transfers and cash book, customer profiles, visit history, loyalty points (earn/redeem/expiry) and credit ledger with ageing, accounts payable and receivable (due dates, partial payments, ageing, statements, credit limits), purchase and sales returns, stock batches with expiry, physical stock count, bank and QR reconciliation, cash flow statement, kitchen and bar P/L, item-wise profit, table/cabin performance, import of the old Google Sheets (Sales, Kharcha with category mapping, RENT).

PHASE 3 (after review): staff, shifts, PIN attendance, leave, advances, payroll and payslips, automatic double-entry ledger, P/L, balance sheet, IRD VAT sales/purchase books, QR self-ordering with staff approval, payment gateway integration.

DEVELOPMENT STANDARDS (Section 9A, mandatory)

- Modular monolith, service/action classes, form requests, policies; no logic in controllers.
- PSR-12 (Pint), Larastan level 6+, ESLint/Prettier, REST /api/v1 with OpenAPI 3 spec.
- Money DECIMAL(12,2); bills, payments, stock and ledger rows append-only; FK + indexes.
- OWASP Top 10 / ASVS L2, rate-limited PIN login, encrypted sensitive fields, secrets only in .env.
- IRD e-billing rules for invoice numbering; WCAG 2.1 AA on the public QR menu.
- Pest tests (light), Conventional Commits, GitHub Actions CI (Pint, Larastan, tests), SemVer tags, Sentry, daily off-site backup.
- Each module is done only when it meets the acceptance criteria in 9A.3.

WAY OF WORKING

- Start with the database migrations and seeders for Phase 1, then backend services and tests for billing totals, then screens.
- Keep one simple feature test per module; do not over-test.
- After Phase 1, stop, list what was built against the FR IDs, and wait for approval.
```
