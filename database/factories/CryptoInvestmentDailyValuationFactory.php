<?php

namespace Database\Factories;

use App\Models\CryptoInvestment;
use App\Models\CryptoInvestmentDailyValuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CryptoInvestmentDailyValuation>
 */
class CryptoInvestmentDailyValuationFactory extends Factory
{
    protected $model = CryptoInvestmentDailyValuation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $escrowBefore = fake()->randomFloat(2, 100, 5000);
        $delta = fake()->randomFloat(2, -200, 200);
        $escrowAfter = round(max(0, $escrowBefore + $delta), 2);

        return [
            'crypto_investment_id' => CryptoInvestment::factory(),
            'valuation_date' => now()->subDay()->toDateString(),
            'price_usd' => fake()->randomFloat(4, 100, 100000),
            'escrow_before_usd' => $escrowBefore,
            'escrow_after_usd' => $escrowAfter,
            'delta_usd' => round($escrowAfter - $escrowBefore, 2),
            'was_clamped_by_max_loss' => false,
            'created_at' => now(),
        ];
    }
}
