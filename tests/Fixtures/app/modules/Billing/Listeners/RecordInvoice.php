<?php

namespace App\Billing\Listeners;

use App\Billing\Events\InvoicePaid;

class RecordInvoice
{
    /** @var list<string> */
    public static array $seen = [];

    public function handle(InvoicePaid $event): void
    {
        self::$seen[] = $event->reference;
    }
}
