<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\BasketController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

// KIOSK ROUTES - idle screen, shopping screen, start over
Route::get('/', [KioskController::class, 'welcome'])->name('welcome');
Route::post('/start', [KioskController::class, 'start'])->name('kiosk.start');
Route::get('/kiosk', [KioskController::class, 'index'])->name('kiosk');        // ?q=&type=&class= search and filters
Route::post('/reset', [KioskController::class, 'reset'])->name('kiosk.reset');

// BASKET ROUTES
Route::post('/kiosk/basket/{medicine}', [BasketController::class, 'store'])->name('basket.store');
Route::put('/kiosk/basket/{medicine}', [BasketController::class, 'update'])->name('basket.update');
Route::delete('/kiosk/basket/{medicine}', [BasketController::class, 'destroy'])->name('basket.destroy');

// MEMBER ROUTES - mock loyalty-card sign in
Route::post('/kiosk/member', [MemberController::class, 'store'])->middleware('throttle:member-sign-in')->name('member.store');
Route::delete('/kiosk/member', [MemberController::class, 'destroy'])->name('member.destroy');

// ASSISTANT ROUTES - AI alternatives for out-of-stock medicines
Route::post('/kiosk/assistant', [AssistantController::class, 'store'])->middleware('throttle:assistant')->name('assistant.store');

// CHECKOUT ROUTES
Route::post('/kiosk/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/receipt/{order:order_number}', [ReceiptController::class, 'show'])->middleware('signed')->name('receipt');

// STAFF ROUTES - not linked from the kiosk. On the kiosk, double-tap the logo
// on the idle screen (see welcome.blade.php); other devices use the URL.
Route::prefix(config('kiosk.admin.path'))->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:staff-login')->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::patch('/orders/{order:order_number}/complete', [Admin\OrderController::class, 'complete'])->name('orders.complete');
        Route::patch('/orders/{order:order_number}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');
    });
});
