<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\SalaryConfirmationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SharedPageController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UnclassifiedController;
use Illuminate\Support\Facades\Route;

// OTP routes (no middleware)
Route::get('/otp', [OtpController::class, 'showForm'])->name('otp.form');
Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify')->middleware('throttle:5,1');
Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend')->middleware('throttle:5,1');
Route::post('/otp/logout', [OtpController::class, 'logout'])->name('otp.logout');

// Shared page (no OTP middleware)
Route::get('/shared', [SharedPageController::class, 'login'])->name('shared.login');
Route::post('/shared/verify', [SharedPageController::class, 'verify'])->name('shared.verify')->middleware('throttle:5,1');
Route::get('/shared/view', [SharedPageController::class, 'show'])->name('shared.show');
Route::get('/shared/transactions/{category}', [SharedPageController::class, 'categoryTransactions'])->name('shared.transactions');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
Route::post('/transactions/{transaction}/classify', [TransactionController::class, 'classify'])->name('transactions.classify');

Route::get('/unclassified', [UnclassifiedController::class, 'index'])->name('unclassified.index');
Route::post('/salary-confirmations/{salaryConfirmation}', [SalaryConfirmationController::class, 'resolve'])->name('salary-confirmations.resolve');

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::post('/categories/income', [CategoryController::class, 'updateIncome'])->name('categories.updateIncome');
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::post('/categories/{category}/update', [CategoryController::class, 'update'])->name('categories.update');
Route::post('/categories/{category}/toggle', [CategoryController::class, 'toggleActive'])->name('categories.toggle');
Route::post('/categories/{category}/delete', [CategoryController::class, 'destroy'])->name('categories.destroy');

Route::get('/summary', [SummaryController::class, 'index'])->name('summary.index');
Route::get('/summary/transactions/{category}', [SummaryController::class, 'categoryTransactions'])->name('summary.transactions');
Route::post('/summary/archive', [SummaryController::class, 'archive'])->name('summary.archive');

Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
Route::post('/settings/test-telegram', [SettingController::class, 'testTelegram'])->name('settings.testTelegram');
Route::post('/settings/telegram-webhook', [SettingController::class, 'setTelegramWebhook'])->name('settings.telegramWebhook');
Route::post('/settings/shared', [SettingController::class, 'updateShared'])->name('settings.updateShared');
