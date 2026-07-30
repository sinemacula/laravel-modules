<?php

namespace App\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;

class InvoiceController
{
    public function show(): JsonResponse
    {
        return response()->json(['status' => 'billing-invoice-ok']);
    }
}
