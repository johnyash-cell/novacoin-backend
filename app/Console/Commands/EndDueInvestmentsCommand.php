<?php

namespace App\Console\Commands;

use App\Services\Investment\ProcessesInvestmentDailyAccrualAndMaturityPayouts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('investments:end-due')]
#[Description('Accrue flat daily investment returns and settle matured payouts to user wallets')]
class EndDueInvestmentsCommand extends Command
{
    public function handle(ProcessesInvestmentDailyAccrualAndMaturityPayouts $processor): int
    {
        $result = $processor->processAll();

        $this->info("Created {$result['daily_logs_created']} daily earning log(s).");
        $this->info("Completed {$result['payouts_completed']} investment payout(s).");

        return self::SUCCESS;
    }
}
