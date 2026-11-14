<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Billing\BillController;
use App\Http\Controllers\Dues\CustomerController;
use App\Http\Controllers\Floor\FloorController;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Http\Controllers\Menu\MenuCategoryController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Menu\StationController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// Quick PIN switch on registered terminals (D-06).
Route::middleware('guest')->group(function () {
    Route::get('pin', [PinLoginController::class, 'create'])->name('pin.create');
    Route::post('pin', [PinLoginController::class, 'store'])->middleware('throttle:pin')->name('pin.store');
});

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

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
    });

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
