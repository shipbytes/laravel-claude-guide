<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Middleware\VerifyPaymentWebhook;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/invoices');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
});

Route::post('/webhooks/payments', PaymentWebhookController::class)
    ->middleware(VerifyPaymentWebhook::class)
    ->name('webhooks.payments');
