<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/invoices/{id}/download', [DashboardController::class, 'downloadInvoice'])->name('invoices.download');
Route::post('/dashboard/simulate-webhook', [DashboardController::class, 'simulateWebhook'])->name('dashboard.simulate-webhook');
Route::post('/dashboard/retry-dlq', [DashboardController::class, 'retryDlq'])->name('dashboard.retry-dlq');
