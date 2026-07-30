<?php

namespace App\Reporting\Listeners;

use App\Reporting\Events\ReportRequested;

class RecordReport
{
    /** @var list<string> */
    public static array $seen = [];

    public function handle(ReportRequested $event): void
    {
        self::$seen[] = $event->period;
    }
}
