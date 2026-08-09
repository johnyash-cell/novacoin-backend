<?php

namespace Database\Factories;

use App\Enums\CryptoInvestmentFeeChargeSource;
use App\Enums\CryptoInvestmentFeeType;
use App\Enums\InvestmentStatus;
use App\Models\CryptoInvestment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CryptoInvestment>
 */
class CryptoInvestmentFactory extends Factory
{
    protected $model = CryptoInvestment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountUsd = fake()->randomFloat(2, 100, 5000);
        $feeUsd = fake()->randomFloat(2, 1, 50);
        $committedUsd = round($amountUsd, 2);
        $entryPriceUsd = fake()->randomFloat(4, 100, 100000);
        $units = $entryPriceUsd > 0 ? $committedUsd / $entryPriceUsd : 0;
        $termDays = fake()->randomElement([7, 14, 30, 90]);
        $startedAt = now()->subDays(fake()->numberBetween(1, 10));

        return [
            'user_id' => User::factory(),
            'coingecko_asset_id' => 'bitcoin',
            'asset_symbol' => 'BTC',
            'asset_label' => 'Bitcoin',
            'amount_usd' => $amountUsd,
            'fee_type' => CryptoInvestmentFeeType::Percent->value,
            'fee_value' => 1,
            'fee_charge_source' => CryptoInvestmentFeeChargeSource::FromWallet->value,
            'fee_usd' => $feeUsd,
            'committed_usd' => $committedUsd,
            'entry_price_usd' => $entryPriceUsd,
            'units' => $units,
            'current_escrow_usd' => $committedUsd,
            'last_price_usd' => $entryPriceUsd,
            'max_loss_enabled' => false,
            'max_loss_floor_usd' => null,
            'term_days' => $termDays,
            'status' => InvestmentStatus::Active->value,
            'started_at' => $startedAt,
            'matures_at' => $startedAt->copy()->addDays($termDays),
            'ended_at' => null,
            'payout_completed_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(function (array $attributes): array {
            $termDays = $attributes['term_days'] ?? 30;
            $startedAt = now()->subDays(5);

            return [
                'status' => InvestmentStatus::Active->value,
                'started_at' => $startedAt,
                'matures_at' => $startedAt->copy()->addDays($termDays),
                'ended_at' => null,
                'payout_completed_at' => null,
            ];
        });
    }

    public function ended(): static
    {
        return $this->state(function (array $attributes): array {
            $termDays = $attributes['term_days'] ?? 30;
            $startedAt = now()->subDays($termDays + 5);
            $maturesAt = $startedAt->copy()->addDays($termDays);

            return [
                'status' => InvestmentStatus::Ended->value,
                'started_at' => $startedAt,
                'matures_at' => $maturesAt,
                'ended_at' => $maturesAt,
                'payout_completed_at' => $maturesAt,
            ];
        });
    }

    public function withMaxLossFloor(float $floorUsd): static
    {
        return $this->state(fn (): array => [
            'max_loss_enabled' => true,
            'max_loss_floor_usd' => $floorUsd,
        ]);
    }
}
