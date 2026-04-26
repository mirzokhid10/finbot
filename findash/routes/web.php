<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\TelegramController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
Route::post('/quick-add', [DashboardController::class, 'quickAdd'])->name('quick.add');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});




Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
Route::resource('transactions', TransactionController::class);

Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

// Telegram OAuth routes
Route::get('/auth/telegram', [TelegramController::class, 'redirectToTelegram'])->name('telegram.login');
Route::get('/auth/telegram/callback', [TelegramController::class, 'handleCallback'])->name('telegram.callback');
Route::post('/auth/telegram/popup-callback', [TelegramController::class, 'handlePopupCallback'])->name('telegram.popup.callback');
Route::post('/logout', [TelegramController::class, 'logout'])->name('logout');
require __DIR__ . '/auth.php';
