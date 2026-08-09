<?php

namespace App\Console\Commands\CryptoInvestments;

use App\Models\CryptoInvestment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crypto-investments:mark-to-market-and-settle')]
#[Description('Mark unpaid crypto investments to market and settle matured payouts')]
class MarkToMarketAndSettleCryptoInvestmentsCommand extends Command
{
    public function handle(): int
    {
        $result = CryptoInvestment::processAllDue();

        $this->info(
            "Created {$result['valuations_created']} valuation(s); completed {$result['payouts_completed']} payout(s).",
        );

        return self::SUCCESS;
    }
}
