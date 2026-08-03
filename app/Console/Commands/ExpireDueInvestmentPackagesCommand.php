<?php

namespace App\Console\Commands;

use App\Models\InvestmentPackage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('investment-packages:expire-due')]
#[Description('Mark investment packages as expired when expires_at is due')]
class ExpireDueInvestmentPackagesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredCount = InvestmentPackage::expireAllDue();

        $this->info("Expired {$expiredCount} investment package(s).");

        return self::SUCCESS;
    }
}
