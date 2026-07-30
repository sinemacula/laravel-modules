<?php

namespace App\Billing\Console\Commands;

use Illuminate\Console\Command;

class BillingPingCommand extends Command
{
    protected $signature = 'billing:ping';

    protected $description = 'Ping from the billing module';

    public function handle(): int
    {
        $this->line('billing-command-ok');

        return self::SUCCESS;
    }
}
