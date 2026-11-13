<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PinLoginController;
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

    Route::middleware('can:terminals.manage')->group(function () {
        Route::get('terminals', [TerminalController::class, 'index'])->name('terminals.index');
        Route::post('terminals', [TerminalController::class, 'store'])->name('terminals.store');
        Route::delete('terminals/{terminal}', [TerminalController::class, 'destroy'])->name('terminals.destroy');
    });
});

require __DIR__.'/settings.php';
