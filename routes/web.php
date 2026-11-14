<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Billing\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dues\CustomerController;
use App\Http\Controllers\Floor\FloorController;
use App\Http\Controllers\Floor\ReservationController;
use App\Http\Controllers\Guest\GuestController;
use App\Http\Controllers\Guest\ServiceCallController;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Http\Controllers\Menu\MenuCategoryController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Menu\StationController;
use App\Http\Controllers\Money\CashBookController;
use App\Http\Controllers\Money\DayController;
use App\Http\Controllers\Money\ExpenseController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Staff\AttendanceController;
use App\Http\Controllers\Staff\PayrollController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Stock\PurchaseController;
use App\Http\Controllers\Stock\PurchaseOrderController;
use App\Http\Controllers\Stock\RecipeController;
use App\Http\Controllers\Stock\StockItemController;
use App\Http\Controllers\Stock\SupplierController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// Public QR page per seat: live menu and call waiter (step 14, FR-5.2, M16).
Route::middleware('throttle:guest')->group(function () {
    Route::get('t/{token}', [GuestController::class, 'show'])->name('guest.table');
    Route::get('t/{token}/calls/{publicId}', [GuestController::class, 'status'])->name('guest.calls.status');
    Route::post('t/{token}/calls', [GuestController::class, 'call'])->middleware('throttle:guest-call')->name('guest.calls.store');
});

// Quick PIN switch on registered terminals (D-06).
Route::middleware('guest')->group(function () {
    Route::get('pin', [PinLoginController::class, 'create'])->name('pin.create');
    Route::post('pin', [PinLoginController::class, 'store'])->middleware('throttle:pin')->name('pin.store');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->middleware('throttle:20,1')->name('users.password');
        Route::delete('users/{user}/pin', [UserController::class, 'clearPin'])->name('users.pin.clear');
        Route::patch('users/{user}/pin/unlock', [UserController::class, 'unlockPin'])->name('users.pin.unlock');
    });

    // Restaurant, tax and billing settings (step 4) — Super Admin only.
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('admin/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::put('admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    });

    // Floor (step 3).
    Route::middleware('can:floor.view')->group(function () {
        Route::get('floor', [FloorController::class, 'index'])->name('floor.index');
        Route::patch('floor/tables/{table}/status', [FloorController::class, 'updateStatus'])->name('floor.tables.status');
    });
    Route::middleware('can:floor.manage')->prefix('floor')->name('floor.')->group(function () {
        Route::post('zones', [FloorController::class, 'storeZone'])->name('zones.store');
        Route::put('zones/{zone}', [FloorController::class, 'updateZone'])->name('zones.update');
        Route::post('tables', [FloorController::class, 'storeTable'])->name('tables.store');
        Route::put('tables/{table}', [FloorController::class, 'updateTable'])->name('tables.update');
        Route::post('tables/{table}/qr', [FloorController::class, 'regenerateQr'])->name('tables.qr');
        Route::get('qr-cards', [FloorController::class, 'qrCards'])->name('qr-cards');
    });

    // POS orders + KOT (step 5).
    Route::middleware('can:pos.use')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('orders', [PosController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [PosController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/items', [PosController::class, 'addItem'])->name('orders.items.store');
        Route::post('orders/{order}/kot', [PosController::class, 'sendKot'])->name('orders.kot');
        Route::post('orders/{order}/move', [PosController::class, 'move'])->name('orders.move');
        Route::post('orders/{order}/merge', [PosController::class, 'merge'])->name('orders.merge');
        Route::post('orders/{order}/cancel', [PosController::class, 'cancel'])->name('orders.cancel');
        Route::patch('order-items/{orderItem}', [PosController::class, 'updateItem'])->name('items.update');
        Route::delete('order-items/{orderItem}', [PosController::class, 'removeItem'])->name('items.destroy');
        Route::post('order-items/{orderItem}/void', [PosController::class, 'voidItem'])->middleware('throttle:10,1')->name('items.void');
        Route::patch('order-items/{orderItem}/served', [PosController::class, 'markServed'])->name('items.served');
        Route::get('kots/{kot}/print', [PosController::class, 'printKot'])->name('kots.print');
    });

    // Billing (step 7).
    Route::middleware('can:billing.take_payment')->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillController::class, 'index'])->name('index');
        Route::get('orders/{order}', [BillController::class, 'checkout'])->name('checkout');
        Route::post('orders/{order}/settle', [BillController::class, 'settle'])->middleware('throttle:20,1')->name('settle');
        Route::get('bills/{bill}/print', [BillController::class, 'print'])->name('bills.print');
        Route::post('bills/{bill}/return', [BillController::class, 'returnSale'])->middleware('throttle:10,1')->name('bills.return');
    });

    // Customers, groups and dues (step 8).
    Route::middleware('can:dues.manage')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/payments', [CustomerController::class, 'receive'])->middleware('throttle:30,1')->name('customers.payments.store');
        Route::post('customers/{customer}/move', [CustomerController::class, 'move'])->middleware('throttle:10,1')->name('customers.move');
        Route::post('customer-groups', [CustomerController::class, 'storeGroup'])->name('customer-groups.store');
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::get('customer-receipts/{entry}/print', [CustomerController::class, 'receipt'])->name('customers.receipts.print');
    });

    // Double-entry ledger, manual journals (FR-13), Super Admin only.
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('accounting', [AccountingController::class, 'index'])->name('accounting.index');
        Route::post('accounting/journals', [AccountingController::class, 'store'])->name('accounting.journals.store');
    });

    // Money: cash book, expenses, day open/close (step 10).
    Route::middleware('can:cash.manage')->prefix('money')->name('money.')->group(function () {
        Route::get('cash-book', [CashBookController::class, 'index'])->name('cash-book');
        Route::post('cash-book/entries', [CashBookController::class, 'manual'])->name('cash-book.entries');
        Route::post('cash-book/transfers', [CashBookController::class, 'transfer'])->name('cash-book.transfers');
        Route::patch('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
        Route::post('recurring-expenses', [ExpenseController::class, 'storeRecurring'])->name('recurring.store');
        Route::put('recurring-expenses/{recurringExpense}', [ExpenseController::class, 'updateRecurring'])->name('recurring.update');
    });
    Route::middleware('can:expenses.create')->prefix('money')->name('money.')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware('throttle:30,1')->name('expenses.store');
        Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
    });
    Route::middleware('can:cash.day')->prefix('money')->name('money.')->group(function () {
        Route::get('day', [DayController::class, 'index'])->name('day');
        Route::post('day/open', [DayController::class, 'open'])->name('day.open');
        Route::post('day/close', [DayController::class, 'close'])->name('day.close');
    });

    // Staff, attendance, payroll (step 13).
    Route::middleware('can:staff.manage')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::put('{staff}', [StaffController::class, 'update'])->whereNumber('staff')->name('update');
        Route::post('{staff}/advances', [StaffController::class, 'advance'])->whereNumber('staff')->name('advances.store');
        Route::put('{staff}/attendance', [StaffController::class, 'markAttendance'])->whereNumber('staff')->name('attendance.mark');
        Route::post('leaves', [StaffController::class, 'storeLeave'])->name('leaves.store');
        Route::patch('leaves/{leave}', [StaffController::class, 'decideLeave'])->name('leaves.update');
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll');
        Route::post('payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::patch('payslips/{payslip}', [PayrollController::class, 'update'])->name('payslips.update');
        Route::post('payslips/{payslip}/pay', [PayrollController::class, 'pay'])->name('payslips.pay');
        Route::get('payslips/{payslip}/print', [PayrollController::class, 'print'])->name('payslips.print');
    });
    Route::middleware('can:staff.attendance')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/punch', [AttendanceController::class, 'punch'])->middleware('throttle:10,1')->name('attendance.punch');
    });

    // Reports (step 11). Owner-only reports are checked inside the controller.
    Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('show');
        Route::get('{report}/excel', [ReportController::class, 'excel'])->where('report', '[a-z-]+')->middleware('throttle:30,1')->name('excel');
        Route::get('{report}/pdf', [ReportController::class, 'pdf'])->where('report', '[a-z-]+')->middleware('throttle:30,1')->name('pdf');
    });

    // Stock & purchase (step 9).
    Route::middleware('can:stock.manage')->prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [StockItemController::class, 'index'])->name('items.index');
        Route::post('items', [StockItemController::class, 'store'])->name('items.store');
        Route::put('items/{stockItem}', [StockItemController::class, 'update'])->name('items.update');
        Route::post('items/{stockItem}/adjust', [StockItemController::class, 'adjust'])->name('items.adjust');

        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');

        Route::get('orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::post('orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::patch('orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('orders.print');

        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'pay'])->name('suppliers.payments.store');
        Route::post('suppliers/{supplier}/returns', [SupplierController::class, 'returnGoods'])->name('suppliers.returns.store');

        Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::put('recipes/{menuItem}', [RecipeController::class, 'update'])->name('recipes.update');
    });

    // Reservations with advance (FR-1.4).
    Route::middleware('can:floor.view')->prefix('reservations')->name('floor.reservations.')->group(function () {
        Route::get('/', [ReservationController::class, 'index'])->name('index');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
        Route::post('{reservation}/seat', [ReservationController::class, 'seat'])->name('seat');
        Route::post('{reservation}/close', [ReservationController::class, 'close'])->name('close');
    });

    // Guest calls, staff side (step 14).
    Route::middleware('can:pos.use')->prefix('calls')->name('calls.')->group(function () {
        Route::get('pending', [ServiceCallController::class, 'pending'])->name('pending');
        Route::patch('{call}/accept', [ServiceCallController::class, 'accept'])->name('accept');
        Route::patch('{call}/done', [ServiceCallController::class, 'done'])->name('done');
    });
    Route::post('calls/ping', [ServiceCallController::class, 'ping'])->middleware('throttle:30,1')->name('calls.ping');

    // Kitchen & bar display (step 6).
    Route::middleware('can:kitchen.view')->prefix('kitchen')->name('kitchen.')->group(function () {
        Route::get('/', [KitchenController::class, 'index'])->name('index');
        Route::patch('items/{orderItem}/bump', [KitchenController::class, 'bump'])->name('items.bump');
        Route::patch('kots/{kot}/bump', [KitchenController::class, 'bumpKot'])->name('kots.bump');
    });

    // Menu (step 2). Everyone with menu.view sees it; only menu.manage edits; sold-out toggle has its own permission.
    Route::get('menu', [MenuController::class, 'index'])->middleware('can:menu.view')->name('menu.index');
    Route::patch('menu/items/{item}/availability', [MenuItemController::class, 'toggleAvailability'])
        ->middleware('can:menu.availability')->name('menu.items.availability');

    Route::middleware('can:menu.manage')->prefix('menu')->name('menu.')->group(function () {
        Route::post('items', [MenuItemController::class, 'store'])->name('items.store');
        Route::put('items/{item}', [MenuItemController::class, 'update'])->name('items.update');
        Route::patch('items/{item}/archive', [MenuItemController::class, 'archive'])->name('items.archive');
        Route::patch('items/{item}/restore', [MenuItemController::class, 'restore'])->name('items.restore');
        Route::post('items/{item}/image', [MenuItemController::class, 'uploadImage'])->middleware('throttle:30,1')->name('items.image');
        Route::delete('items/{item}/image', [MenuItemController::class, 'removeImage'])->name('items.image.destroy');

        Route::post('categories', [MenuCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [MenuCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [MenuCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('stations', [StationController::class, 'store'])->name('stations.store');
        Route::put('stations/{station}', [StationController::class, 'update'])->name('stations.update');
    });

    Route::middleware('can:terminals.manage')->group(function () {
        Route::get('terminals', [TerminalController::class, 'index'])->name('terminals.index');
        Route::post('terminals', [TerminalController::class, 'store'])->name('terminals.store');
        Route::delete('terminals/{terminal}', [TerminalController::class, 'destroy'])->name('terminals.destroy');
    });
});

require __DIR__.'/settings.php';
