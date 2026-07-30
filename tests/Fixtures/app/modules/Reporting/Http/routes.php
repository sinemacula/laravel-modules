<?php

use App\Reporting\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/reporting/summary', [SummaryController::class, 'show']);
