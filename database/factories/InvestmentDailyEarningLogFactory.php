<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentDailyEarningLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentDailyEarningLog>
 */
class InvestmentDailyEarningLogFactory extends Factory
{
    protected $model = InvestmentDailyEarningLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountUsd = fake()->randomFloat(2, 1, 50);

        return [
            'investment_id' => Investment::factory(),
            'earning_date' => now()->toDateString(),
            'amount_usd' => $amountUsd,
            'accrued_return_after_usd' => $amountUsd,
            'created_at' => now(),
        ];
    }
}
