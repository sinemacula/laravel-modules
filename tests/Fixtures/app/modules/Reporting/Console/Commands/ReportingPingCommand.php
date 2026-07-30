<?php

namespace App\Reporting\Console\Commands;

use Illuminate\Console\Command;

class ReportingPingCommand extends Command
{
    protected $signature = 'reporting:ping';

    protected $description = 'Ping from the reporting module';

    public function handle(): int
    {
        $this->line('reporting-command-ok');

        return self::SUCCESS;
    }
}
