WOODSTONE Restaurant Management System
COMPLETE SOFTWARE BLUEPRINT • WEB + DESKTOP • NEPAL
WOODSTONE
Restaurant Management System
ONE RESTAURANT • ONE DATABASE • COMPLETE BUSINESS CONTROL
Web Dashboard Desktop POS
Business
Woodstone Restaurant & Cafe
Location
Butwal-08, Sukkhanagar
Currency
Nepalese Rupees (NPR)
Deployment
Single restaurant
The goal is to create a centralized system where the owner can control the entire restaurant, while cashiers, kitchen staff, storekeepers and managers use only the functions they need.
This is designed around your restaurant's food, snacks, momo, chicken, drinks, table, cabin and open-green-space operations.

1. Complete system architecture
   I recommend a modular monolithic architecture initially. It is easier and less expensive to build and maintain than a complicated microservices system, while still allowing future expansion.
   WOODSTONE APPLICATION
   Web Admin
   Owner / Manager
   Desktop POS
   Cashier
   Kitchen Display
   Kitchen staff
   Store Interface
   Inventory staff
   APPLICATION BACKEND
   Authentication • Business rules • API • Reporting
   CENTRAL DATABASE
   PostgreSQL • Orders • Payments • Stock • Finance • Users
   Cloud Backup Receipt / Kitchen Printer
   Important operating rule: All devices should use the same business records. A cashier's order must appear in the kitchen, inventory and financial reports without entering it again.
   Recommended technology stack
   The desktop application should support offline operation using a local database and a synchronization queue. The web dashboard can access the central database when internet is available. Offline transactions must have unique IDs and conflict checks before synchronization.
2. Main software modules
   I recommend 18 modules, organized into six departments.
   A. Sales & Restaurant Operations
   POS Billing & Orders
   Table Management
   Kitchen Display System
   Reservation & Cabin Management
   Menu & Price Management
   B. Inventory & Procurement
   Inventory & Stock
   Purchase Management
   Supplier Management
   Recipe & Food Cost Management
   C. Finance & Accounting
   Cash Flow Management
   Income & Expense Management
   Accounts Payable & Receivable
   Profit & Loss Reporting
   D. Human Resources
   Employee Management
   Attendance, Shifts & Payroll
   E. Customer Management
3. Customer Database & Loyalty
   F. Administration & Analytics
   Reports & Business Analytics
   User Permissions, Audit & Settings
4. Detailed module specifications
   A. POS billing and order management
   This is the most important operational module.
   Required functions:
   Create new order.
   Select table, cabin or takeaway.
   Add menu items and quantities.
   Add special instructions.
   Apply authorized discounts.
   Split bills and merge orders.
   Accept cash, QR/bank transfer and card payments.
   Print customer receipts.
   Cancel or refund with authorization.
   Reprint previous invoices.
   Woodstone order types
   B. Kitchen Display System (KDS)
   When the cashier confirms an order, the kitchen receives it automatically.
   Kitchen statuses:
   New order
   Accepted
   Preparing
   Ready
   Served
   Kitchen should see separate sections for momo, snacks, chicken and drinks if those are assigned to different preparation stations.
   C. Menu management
   Your existing Woodstone menu should be entered into the system as master data.
   Every menu item should have:
   Item name and code
   Category and subcategory
   Selling price
   Recipe and ingredient quantities
   Preparation station
   Tax configuration
   Availability status
   Food cost and gross margin
   D. Inventory and purchase management
   Inventory should track raw materials rather than just finished dishes.
   Example:
   Features:
   Opening stock.
   Stock received.
   Stock issued.
   Automatic recipe deduction.
   Wastage and spoilage.
   Stock adjustment.
   Low-stock alerts.
   Expiry tracking.
   Physical stock verification.
   Supplier purchase history.
   E. Cash flow and finance
   This module must provide a complete financial picture.
   Reports should include:
   Daily cash book.
   Cashier opening and closing balances.
   Bank and QR reconciliation.
   Daily expenses.
   Supplier outstanding payments.
   Customer outstanding balances.
   Profit and loss.
   Cash flow statement.
   Monthly financial summary.
   Important distinction: Sales revenue, accounting profit and cash flow are different. A credit purchase, unpaid bill or owner capital contribution must not be treated as an ordinary cash sale or operating income.
   F. Staff management
   Employee profile.
   Job designation.
   Salary structure.
   Attendance.
   Shift scheduling.
   Leave records.
   Advance salary.
   Payroll.
   Employee activity logs.
5. Owner dashboard and reporting
   The owner should see these indicators immediately after login:
   Reports should be filterable by today, yesterday, week, month, quarter and custom date range.
   Most valuable analytics:
   Top 10 selling dishes.
   Slow-moving menu items.
   Food cost percentage.
   Category-wise revenue.
   Hourly sales trends.
   Staff-wise sales.
   Wastage percentage.
   Stock purchase trends.
   Monthly profit comparison.
6. Database structure
   This is the foundation of the software. I recommend PostgreSQL with a relational database design.
   The following tables should be created and connected using primary keys and foreign keys.
   Database relationship overview
   Core database tables
   Additional database rules
   A production-ready system needs some additional tables:
   order_status_history – every order status change.
   payment_allocations – supports split payments.
   stock_batches – expiry dates and batch costs.
   stock_adjustments – physical stock differences.
   purchase_returns – supplier returns.
   sales_returns – refunds and returned items.
   journal_entries and journal_lines – double-entry accounting.
   business_settings – restaurant information and preferences.
   sync_queue – offline desktop transaction synchronization.
   number_sequences – unique invoice numbering.
   printer_settings – billing and kitchen printer configurations.
   Database design rules
   Every transaction gets a unique ID.
   Never permanently delete completed sales or financial records.
   Corrections should use cancellation, reversal or adjustment entries.
   Store money as decimal values, not floating-point numbers.
   Save the actual selling price and tax details on each order item so historical invoices do not change when the menu price changes.
   Every stock movement should have a source transaction.
   Maintain timestamps and user IDs for important changes.
   Use database transactions when completing sales, recording payments and deducting inventory.
7. How the modules communicate
   The following workflows connect the entire system.
   Workflow 1: Customer order
   Cashier creates POS order.
   Order items are stored in the database.
   Kitchen receives preparation tickets.
   Kitchen marks food ready.
   Cashier generates invoice and collects payment.
   Sales and payment reports update.
   Workflow 2: Automatic stock deduction
   Customer orders one Chicken Momo.
   System reads the approved Chicken Momo recipe.
   Required ingredients are deducted from stock.
   Stock movement is recorded.
   Low-stock alert appears if the reorder level is reached.
   Workflow 3: Daily financial closing
   Cashier counts physical cash.
   System compares actual cash against recorded transactions.
   QR and bank payments are reconciled.
   Expenses and supplier payments are recorded.
   Owner receives daily sales and cash-flow reports.
8. User roles and permissions
   The owner should have complete access. Other employees should have restricted permissions.
   Additional security requirements:
   Individual login accounts.
   Strong password hashing.
   Permission-based access.
   Two-factor authentication for owner access.
   Audit trail for discounts, voids and refunds.
   Automatic logout after inactivity.
   Encrypted backups.
   No shared administrator passwords.
9. Web and desktop screen design
   Owner web dashboard
   WOODSTONE
   MAIN NAVIGATION
   Dashboard POS Kitchen
   Tables Inventory Purchases
   Finance Employees Reports
   Today's Business
   Sales
   Rs. 25,500
   Orders
   68
   Illustrative interface, not connected to live restaurant data.
   Desktop POS screen
   The POS should have three main sections:
   Left: Menu categories and food items.
   Center: Current order and quantities.
   Right: Table details, subtotal, discounts, tax and payment.
   The cashier should be able to create an order in just a few clicks.
   Other essential screens
10. Nepal-specific requirements
    The software should be prepared for the Nepal business environment.
    Configuration requirements:
    NPR currency and Nepal number formatting.
    Bikram Sambat and Gregorian date display.
    Configurable PAN/VAT invoice details.
    Applicable VAT and tax calculation rules.
    Cash, bank, QR and other payment methods.
    Supplier and customer records.
    Printable invoices.
    Sales and purchase registers.
    Accounting and tax reports.
    Configurable invoice numbering.
    Do not hardcode tax rates or assume that an ordinary POS invoice automatically qualifies as an IRD-compliant electronic tax invoice. Confirm the applicable requirements with an accountant and the Inland Revenue Department before production deployment.
11. Offline and backup architecture
    This is particularly important for restaurant operations.
    Internet interruptions should not stop the cashier from billing customers.
    Recommended arrangement:
    DESKTOP APPLICATION
    Local database + pending transaction queue
    SYNCHRONIZATION ENGINE
    Unique transaction IDs • Conflict checks • Retry
    CENTRAL CLOUD DATABASE
    Shared records and owner web dashboard
    Important safeguards:
    Local desktop billing must continue offline.
    Each device must have its own transaction IDs.
    Do not synchronize the same sale twice.
    Stock deductions must be reconciled when transactions synchronize.
    Show unsynchronized transactions clearly.
    Take daily database backups and periodic encrypted off-site backups.
    Test database restoration regularly.
    Important design decision: For the first version, use one main desktop POS computer as the primary local terminal, with other devices connecting to it on the restaurant's local network. Cloud synchronization should be an additional layer, not the only way to access orders.
12. Implementation roadmap
    I recommend building the software in six phases instead of developing every feature simultaneously.
    Suggested development schedule
    Estimated total: approximately 12 weeks for a first production-ready release, subject to scope, testing and offline complexity.
    Testing before launch
    Test 100 simultaneous order-line operations.
    Test bill cancellation and refunds.
    Verify every recipe's stock deduction.
    Check daily cash reconciliation.
    Test printer failures.
    Test internet disconnection and recovery.
    Verify role permissions.
    Restore a backup on a separate test environment.
    Verify financial reports against sample accounting records.
13. Estimated software development budget
    Indicative Nepal development budget for a custom single-restaurant system:
    These are planning estimates, not verified supplier quotations. Hardware, hosting, maintenance and support may cost extra.
14. Final recommended development priorities
    For Woodstone, I would prioritize these five things:
    Accurate billing: Fast order entry and reliable receipts.
    Kitchen integration: No manual transfer of orders.
    Inventory control: Automatic ingredient deduction and purchase records.
    Cash flow: Know exactly where money comes from and where it goes.
    Owner control: View business performance without being physically present.
15. Next development deliverables
    The software design is ready to move into actual development. These are the next technical deliverables:
    Development checklist
    Development preparation 0/8
    My recommendation: Start with the complete database schema and the actual POS screen design. These two foundations will determine how the other modules connect.
    One important distinction: this is the system blueprint, not a deployed or functioning application yet. The next stage is to build and test the software.
    Prepared around the Woodstone Restaurant & Cafe menu and business operations previously supplied.
