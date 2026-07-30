<?php

namespace App\Reporting\Events;

class ReportRequested
{
    public function __construct(public string $period = 'reporting-event-ok') {}
}
