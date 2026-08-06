<?php

namespace Database\Factories;

use App\Enums\InvestmentStatus;
use App\Models\Investment;
use App\Models\InvestmentPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountUsd = fake()->randomFloat(2, 100, 5000);
        $expectedReturnPercent = fake()->randomFloat(2, 5, 30);
        $expectedReturnAmountUsd = round($amountUsd * ($expectedReturnPercent / 100), 2);
        $termDays = fake()->randomElement([30, 60, 90, 180]);
        $startedAt = now()->subDays(fake()->numberBetween(1, 20));

        return [
            'user_id' => User::factory(),
            'investment_package_id' => InvestmentPackage::factory(),
            'package_name' => fake()->words(3, true),
            'amount_usd' => $amountUsd,
            'expected_return_percent' => $expectedReturnPercent,
            'term_days' => $termDays,
            'expected_return_amount_usd' => $expectedReturnAmountUsd,
            'expected_payout_amount_usd' => round($amountUsd + $expectedReturnAmountUsd, 2),
            'status' => InvestmentStatus::Active->value,
            'started_at' => $startedAt,
            'matures_at' => $startedAt->copy()->addDays($termDays),
            'ended_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(function (array $attributes): array {
            $termDays = $attributes['term_days'] ?? 90;
            $startedAt = now()->subDays(5);

            return [
                'status' => InvestmentStatus::Active->value,
                'started_at' => $startedAt,
                'matures_at' => $startedAt->copy()->addDays($termDays),
                'ended_at' => null,
            ];
        });
    }

    public function ended(): static
    {
        return $this->state(function (array $attributes): array {
            $termDays = $attributes['term_days'] ?? 90;
            $startedAt = now()->subDays($termDays + 5);
            $maturesAt = $startedAt->copy()->addDays($termDays);

            return [
                'status' => InvestmentStatus::Ended->value,
                'started_at' => $startedAt,
                'matures_at' => $maturesAt,
                'ended_at' => $maturesAt,
            ];
        });
    }

    public function dueToEnd(): static
    {
        return $this->state(function (array $attributes): array {
            $termDays = $attributes['term_days'] ?? 90;
            $startedAt = now()->subDays($termDays)->subMinute();

            return [
                'status' => InvestmentStatus::Active->value,
                'started_at' => $startedAt,
                'matures_at' => $startedAt->copy()->addDays($termDays),
                'ended_at' => null,
            ];
        });
    }
}
