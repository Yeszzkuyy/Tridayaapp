<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Route;

// Trash - Manage
Route::middleware('permission:manage-admin')->group(function () {
    Route::patch('/trash/customers/{id}/restore', [TrashController::class, 'restoreCustomer'])->name('trash.restore-customer');
    Route::patch('/trash/projects/{id}/restore', [TrashController::class, 'restoreProject'])->name('trash.restore-project');
    Route::delete('/trash/customers/{id}/delete', [TrashController::class, 'destroyCustomer'])->name('trash.destroy-customer');
    Route::delete('/trash/projects/{id}/delete', [TrashController::class, 'destroyProject'])->name('trash.destroy-project');
    Route::delete('/trash/clear', [TrashController::class, 'clear'])->name('trash.clear');
});

// Trash - View
Route::middleware('permission:view-admin|manage-admin')->group(function () {
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
});

// Invoice, PO, Payment - Manage
Route::middleware('permission:manage-admin')->prefix('admin')->name('admin.')->group(function () {
    // Invoice — data dibuat via upload file di halaman customer
    Route::get('/invoices/{invoice}/edit', [AdminController::class, 'invoicesEdit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [AdminController::class, 'invoicesUpdate'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [AdminController::class, 'invoicesDestroy'])->name('invoices.destroy');

    // PO — data dibuat via upload file di halaman customer
    Route::get('/pos/{purchaseOrder}/edit', [AdminController::class, 'posEdit'])->name('pos.edit');
    Route::put('/pos/{purchaseOrder}', [AdminController::class, 'posUpdate'])->name('pos.update');
    Route::delete('/pos/{purchaseOrder}', [AdminController::class, 'posDestroy'])->name('pos.destroy');

    // Payment — data dibuat via upload file di halaman customer
    Route::delete('/payments/{payment}', [AdminController::class, 'paymentsDestroy'])->name('payments.destroy');
});

// Invoice, PO, Payment - View
Route::middleware('permission:view-admin|manage-admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/invoices', [AdminController::class, 'invoicesIndex'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [AdminController::class, 'invoicesShow'])->name('invoices.show');
    Route::get('/pos', [AdminController::class, 'posIndex'])->name('pos.index');
    Route::get('/pos/{purchaseOrder}', [AdminController::class, 'posShow'])->name('pos.show');
    Route::get('/payments', [AdminController::class, 'paymentsIndex'])->name('payments.index');
    Route::get('/payments/{payment}', [AdminController::class, 'paymentsShow'])->name('payments.show');
});