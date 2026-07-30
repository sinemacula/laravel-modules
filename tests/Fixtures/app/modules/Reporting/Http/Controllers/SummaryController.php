<?php

namespace App\Reporting\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SummaryController
{
    public function show(): JsonResponse
    {
        return response()->json(['status' => 'reporting-summary-ok']);
    }
}
