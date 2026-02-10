<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));



Route::match(['get', 'post'], 'login', [LoginController::class, 'login'])->name('login');
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::prefix('webhook')->group(function () {
    Route::post('orders-create', [WebhookController::class, 'ordersCreate']);
    Route::post('orders-update', [WebhookController::class, 'ordersUpdate']);
    Route::post('orders-cancel', [WebhookController::class, 'ordersCancel']);
    Route::post('orders-delete',[WebhookController::class,'orderDelete']);
    });

Route::middleware(['check_auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/chart-data', [DashboardController::class, 'chartData'])->name('dashboard.chart-data');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order_id}', [OrderController::class, 'show'])->name('orders.show');

    Route::post('sync/orders', [SyncController::class, 'syncOrders'])->name('sync.orders');
});
