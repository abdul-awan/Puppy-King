<?php

/*
|--------------------------------------------------------------------------
| Customer portal routes
|--------------------------------------------------------------------------
|
| Public pages that customers open through the secret link in the e-mails
| they receive (no login). Documents are looked up by their random token.
|
*/

use App\Http\Controllers\Portal\DocumentPortalController;
use App\Http\Controllers\Portal\QuotationPortalController;
use App\Http\Controllers\Portal\TicketPortalController;
use App\Http\Controllers\Portal\WorkOrderPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('p')->name('public.')->middleware('throttle:60,1')->group(function () {
    Route::get('q/{token}', [QuotationPortalController::class, 'show'])->name('quotations.show');
    Route::get('q/{token}/pdf', [QuotationPortalController::class, 'pdf'])->name('quotations.pdf');
    Route::post('q/{token}/accept', [QuotationPortalController::class, 'accept'])->name('quotations.accept');
    Route::post('q/{token}/decline', [QuotationPortalController::class, 'decline'])->name('quotations.decline');

    Route::get('i/{token}', [DocumentPortalController::class, 'invoice'])->name('invoices.show');
    Route::get('i/{token}/pdf', [DocumentPortalController::class, 'invoicePdf'])->name('invoices.pdf');

    Route::get('c/{token}', [DocumentPortalController::class, 'creditNote'])->name('credit-notes.show');
    Route::get('c/{token}/pdf', [DocumentPortalController::class, 'creditNotePdf'])->name('credit-notes.pdf');

    Route::get('w/{token}', [WorkOrderPortalController::class, 'show'])->name('work-orders.show');
    Route::get('w/{token}/pdf', [WorkOrderPortalController::class, 'pdf'])->name('work-orders.pdf');
    Route::post('w/{token}/sign', [WorkOrderPortalController::class, 'sign'])->name('work-orders.sign');

    Route::get('t/{token}', [TicketPortalController::class, 'show'])->name('tickets.show');
    Route::post('t/{token}', [TicketPortalController::class, 'reply'])->name('tickets.reply');

    Route::get('support/{token}', [TicketPortalController::class, 'supportForm'])->name('support.show');
    Route::post('support/{token}', [TicketPortalController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->name('support.store');
});
