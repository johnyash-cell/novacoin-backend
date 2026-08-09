<?php

use App\Enums\CryptoInvestmentFeeType;
use App\Models\Admin;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/markets*' => Http::response([
            [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
            ],
            [
                'id' => 'ethereum',
                'symbol' => 'eth',
                'name' => 'Ethereum',
            ],
            [
                'id' => 'solana',
                'symbol' => 'sol',
                'name' => 'Solana',
            ],
        ], 200),
    ]);
});

function cryptoSettingsAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

it('rejects unauthenticated crypto investment settings requests', function () {
    $this->getJson('/api/admin/crypto-investment-settings')
        ->assertUnauthorized();
});

it('returns default crypto investment settings after migrate seed', function () {
    $token = cryptoSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/crypto-investment-settings')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.term_days', 30)
        ->assertJsonPath('data.fee_type', CryptoInvestmentFeeType::Percent->value)
        ->assertJsonPath('data.max_loss_enabled', true)
        ->assertJsonPath('data.supported_assets.0.coingecko_asset_id', 'bitcoin');
});

it('returns coin options from live top thirty', function () {
    $token = cryptoSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/crypto-investment-settings/coin-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.options.0.value', 'bitcoin');
});

it('updates crypto investment settings including supported assets', function () {
    $token = cryptoSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/crypto-investment-settings', [
            'is_enabled' => true,
            'term_days' => 14,
            'minimum_amount_usd' => 100,
            'maximum_amount_usd' => 25000,
            'fee_type' => CryptoInvestmentFeeType::FixedUsd->value,
            'fee_value' => 25,
            'max_loss_enabled' => true,
            'max_loss_percent' => 40,
            'supported_asset_ids' => ['bitcoin', 'ethereum'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Crypto investment settings updated successfully')
        ->assertJsonPath('data.term_days', 14)
        ->assertJsonPath('data.fee_type', 'fixed_usd')
        ->assertJsonPath('data.fee_value', '25.0000')
        ->assertJsonPath('data.max_loss_percent', '40.00')
        ->assertJsonCount(2, 'data.supported_assets');

    expect(PlatformSetting::query()->where('key', PlatformSetting::CRYPTO_INVESTMENT_TERM_DAYS)->value('value'))
        ->toBe('14');
});

it('rejects supported assets outside the top thirty', function () {
    $token = cryptoSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/crypto-investment-settings', [
            'supported_asset_ids' => ['dogecoin'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['supported_asset_ids']);
});
