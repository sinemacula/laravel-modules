<?php

namespace App\Billing\Events;

class InvoicePaid
{
    public function __construct(public string $reference = 'billing-event-ok') {}
}
