<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Floor\FloorController;
use App\Http\Controllers\Menu\MenuCategoryController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Menu\StationController;
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
