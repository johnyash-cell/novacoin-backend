<?php

namespace Database\Factories;

use App\Models\PlatformCryptoWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformCryptoWallet>
 */
class PlatformCryptoWalletFactory extends Factory
{
    protected $model = PlatformCryptoWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Bitcoin',
            'asset_symbol' => 'BTC',
            'coingecko_asset_id' => 'bitcoin',
            'network_name' => 'Bitcoin',
            'wallet_address' => 'bc1q'.fake()->bothify('????????????????????????'),
            'is_available_for_funding' => true,
            'sort_order' => 1,
            'notes' => null,
        ];
    }

    public function unavailableForFunding(): static
    {
        return $this->state(fn (): array => [
            'is_available_for_funding' => false,
        ]);
    }

    public function ethereum(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Ethereum',
            'asset_symbol' => 'ETH',
            'coingecko_asset_id' => 'ethereum',
            'network_name' => 'ERC20',
            'wallet_address' => '0x'.fake()->bothify('????????????????????????????????????????'),
        ]);
    }
}
