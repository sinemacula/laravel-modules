<?php

use App\Billing\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/billing/invoice', [InvoiceController::class, 'show']);
