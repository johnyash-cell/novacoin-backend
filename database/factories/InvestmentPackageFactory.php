<?php

namespace Database\Factories;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Models\InvestmentPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentPackage>
 */
class InvestmentPackageFactory extends Factory
{
    protected $model = InvestmentPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxParticipants = fake()->numberBetween(10, 200);
        $joinedCount = fake()->numberBetween(0, max(0, $maxParticipants - 1));

        return [
            'name' => fake()->words(3, true),
            'short_pitch' => fake()->sentence(8),
            'description' => fake()->paragraph(),
            'expected_return_percent' => fake()->randomFloat(2, 1, 50),
            'term_days' => fake()->randomElement([30, 60, 90, 180]),
            'minimum_amount_usd' => fake()->randomFloat(2, 50, 500),
            'maximum_amount_usd' => fake()->optional()->randomFloat(2, 1000, 50000),
            'max_participants' => $maxParticipants,
            'joined_count' => $joinedCount,
            'risk_level' => fake()->randomElement(InvestmentPackageRiskLevel::values()),
            'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
            'expires_at' => null,
            'is_featured' => false,
            'highlights' => [fake()->sentence(6), fake()->sentence(5)],
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
        ]);
    }

    public function limited(): static
    {
        return $this->state(fn (): array => [
            'availability_status' => InvestmentPackageAvailabilityStatus::Limited->value,
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (): array => [
            'availability_status' => InvestmentPackageAvailabilityStatus::Full->value,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'availability_status' => InvestmentPackageAvailabilityStatus::Expired->value,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => [
            'is_featured' => true,
        ]);
    }

    public function atCapacity(): static
    {
        return $this->state(function (array $attributes): array {
            $maxParticipants = $attributes['max_participants'] ?? 10;

            return [
                'max_participants' => $maxParticipants,
                'joined_count' => $maxParticipants,
                'availability_status' => InvestmentPackageAvailabilityStatus::Full->value,
            ];
        });
    }

    public function dueToExpire(): static
    {
        return $this->state(fn (): array => [
            'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
