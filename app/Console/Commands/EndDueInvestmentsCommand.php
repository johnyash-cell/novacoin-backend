<?php

namespace App\Console\Commands;

use App\Models\Investment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('investments:end-due')]
#[Description('Mark investments as ended when matures_at is due')]
class EndDueInvestmentsCommand extends Command
{
    public function handle(): int
    {
        $endedCount = Investment::endAllDue();

        $this->info("Ended {$endedCount} investment(s).");

        return self::SUCCESS;
    }
}
