<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceDraftController;
use App\Http\Controllers\InvoiceReminderController;
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
    Route::post('/invoices/draft', InvoiceDraftController::class)->name('invoices.draft');
    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::get('/invoices/{invoice}/reminder', InvoiceReminderController::class)->name('invoices.reminder');
    Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('/assistant', [AssistantController::class, 'store'])->name('assistant.store');
    Route::get('/assistant/{conversation}', [AssistantController::class, 'show'])->name('assistant.show');
    Route::post('/assistant/{conversation}', [AssistantController::class, 'ask'])->name('assistant.ask');
});

Route::post('/webhooks/payments', PaymentWebhookController::class)
    ->middleware(VerifyPaymentWebhook::class)
    ->name('webhooks.payments');
