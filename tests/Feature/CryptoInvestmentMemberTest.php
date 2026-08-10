<?php

use App\Enums\CryptoInvestmentFeeChargeSource;
use App\Enums\CryptoInvestmentFeeType;
use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\CryptoInvestment;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Services\CryptoInvestment\CalculatesCryptoInvestmentFeeAndExposure;
use App\Services\CryptoInvestment\FetchesCoinGeckoMarketSnapshotsForAssetIds;
use App\Services\CryptoInvestment\UpdatesCryptoInvestmentProgramSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    Http::fake([
        'api.coingecko.com/api/v3/coins/markets*' => Http::response([
            [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'current_price' => 50000,
                'price_change_percentage_24h' => 0.2,
                'image' => 'https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png',
            ],
            [
                'id' => 'ethereum',
                'symbol' => 'eth',
                'name' => 'Ethereum',
                'current_price' => 3000,
                'price_change_percentage_24h' => -1.35,
                'image' => 'https://coin-images.coingecko.com/coins/images/279/large/ethereum.png',
            ],
        ], 200),
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'bitcoin' => ['usd' => 50000],
            'ethereum' => ['usd' => 3000],
        ], 200),
    ]);

    app(UpdatesCryptoInvestmentProgramSettings::class)->update([
        'is_enabled' => true,
        'term_days' => 30,
        'minimum_amount_usd' => 100,
        'maximum_amount_usd' => 10000,
        'fee_type' => CryptoInvestmentFeeType::FixedUsd->value,
        'fee_value' => 50,
        'max_loss_enabled' => true,
        'max_loss_percent' => 50,
        'supported_asset_ids' => ['bitcoin', 'ethereum'],
    ]);
});

function cryptoAssetMemberToken(?User $user = null): string
{
    return auth('api')->login($user ?? User::factory()->create());
}

function fundedCryptoAssetMember(User $user, float $balance = 10000): UserWallet
{
    return UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => $balance,
    ]);
}

it('rejects unauthenticated crypto asset routes', function () {
    $this->getJson('/api/crypto-investment-assets')->assertUnauthorized();
    $this->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=7d')->assertUnauthorized();
    $this->getJson('/api/crypto-investment-assets/bitcoin/invest-quote?amount_usd=500&fee_charge_source=from_wallet')
        ->assertUnauthorized();
    $this->postJson('/api/crypto-investment-assets/bitcoin/invest', [
        'amount_usd' => 500,
        'fee_charge_source' => CryptoInvestmentFeeChargeSource::FromWallet->value,
    ])->assertUnauthorized();
    $this->getJson('/api/crypto-investments')->assertUnauthorized();
});

it('calculates fee exposure for both charge sources', function () {
    $calculator = app(CalculatesCryptoInvestmentFeeAndExposure::class);

    $fromAmount = $calculator->calculate(
        amountUsd: 5000,
        feeType: CryptoInvestmentFeeType::FixedUsd->value,
        feeValue: 50,
        feeChargeSource: CryptoInvestmentFeeChargeSource::FromInvestAmount->value,
        maxLossEnabled: true,
        maxLossPercent: 50,
    );

    expect($fromAmount['committed_usd'])->toBe(4950.0)
        ->and($fromAmount['total_debit_usd'])->toBe(5000.0)
        ->and($fromAmount['max_loss_floor_usd'])->toBe(2475.0);

    $fromWallet = $calculator->calculate(
        amountUsd: 5000,
        feeType: CryptoInvestmentFeeType::FixedUsd->value,
        feeValue: 50,
        feeChargeSource: CryptoInvestmentFeeChargeSource::FromWallet->value,
        maxLossEnabled: true,
        maxLossPercent: 50,
    );

    expect($fromWallet['committed_usd'])->toBe(5000.0)
        ->and($fromWallet['total_debit_usd'])->toBe(5050.0)
        ->and($fromWallet['max_loss_floor_usd'])->toBe(2500.0);
});

it('lists supported crypto assets with live prices for members', function () {
    $token = cryptoAssetMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets')
        ->assertSuccessful()
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.assets.0.coingecko_asset_id', 'bitcoin')
        ->assertJsonPath('data.assets.0.current_price_usd', 50000)
        ->assertJsonPath('data.assets.0.price_change_percentage_24h', 0.2)
        ->assertJsonPath(
            'data.assets.0.image_url',
            'https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png',
        )
        ->assertJsonPath('data.assets.0.can_invest', true)
        ->assertJsonPath('data.assets.1.price_change_percentage_24h', -1.35);
});

it('keeps the asset catalog healthy when market enrichment fails', function () {
    $this->mock(FetchesCoinGeckoMarketSnapshotsForAssetIds::class, function ($mock): void {
        $mock->shouldReceive('fetchByAssetIds')->once()->andReturn([]);
    });

    $token = cryptoAssetMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets')
        ->assertSuccessful()
        ->assertJsonPath('data.assets.0.coingecko_asset_id', 'bitcoin')
        ->assertJsonPath('data.assets.0.current_price_usd', 50000)
        ->assertJsonPath('data.assets.0.price_change_percentage_24h', null)
        ->assertJsonPath('data.assets.0.image_url', null)
        ->assertJsonPath('data.assets.0.can_invest', true);
});

it('returns an invest quote against a live asset price', function () {
    $token = cryptoAssetMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/invest-quote?amount_usd=5000&fee_charge_source=from_wallet')
        ->assertSuccessful()
        ->assertJsonPath('data.current_price_usd', 50000)
        ->assertJsonPath('data.fee_usd', 50)
        ->assertJsonPath('data.committed_usd', 5000)
        ->assertJsonPath('data.total_debit_usd', 5050)
        ->assertJsonPath('data.estimated_units', 0.1)
        ->assertJsonPath('data.term_days', 30);
});

it('places a crypto investment against a real asset', function () {
    $user = User::factory()->create();
    $token = cryptoAssetMemberToken($user);
    fundedCryptoAssetMember($user, 10000);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/crypto-investment-assets/bitcoin/invest', [
            'amount_usd' => 5000,
            'fee_charge_source' => CryptoInvestmentFeeChargeSource::FromWallet->value,
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Crypto investment placed successfully')
        ->assertJsonPath('data.coingecko_asset_id', 'bitcoin')
        ->assertJsonPath('data.asset_symbol', 'BTC')
        ->assertJsonPath('data.committed_usd', 5000)
        ->assertJsonPath('data.entry_price_usd', 50000)
        ->assertJsonPath('data.units', 0.1)
        ->assertJsonPath('data.max_loss_floor_usd', 2500)
        ->assertJsonPath('data.effective_status', InvestmentStatus::Active->value);

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(4950.0);

    $holdingId = CryptoInvestment::query()->value('id');

    $this->assertDatabaseHas('wallet_ledger_entries', [
        'crypto_investment_id' => $holdingId,
        'entry_type' => WalletLedgerEntryType::CryptoInvestmentDebit->value,
        'amount' => -5000,
    ]);

    $this->assertDatabaseHas('wallet_ledger_entries', [
        'crypto_investment_id' => $holdingId,
        'entry_type' => WalletLedgerEntryType::CryptoInvestmentFeeDebit->value,
        'amount' => -50,
    ]);
});

it('rejects invest for unsupported assets', function () {
    $user = User::factory()->create();
    $token = cryptoAssetMemberToken($user);
    fundedCryptoAssetMember($user, 10000);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/crypto-investment-assets/dogecoin/invest', [
            'amount_usd' => 500,
            'fee_charge_source' => CryptoInvestmentFeeChargeSource::FromWallet->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coingecko_asset_id']);
});

it('lists holdings and hides other users valuations', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = cryptoAssetMemberToken($user);

    $ownHolding = CryptoInvestment::factory()->active()->create([
        'user_id' => $user->id,
        'coingecko_asset_id' => 'bitcoin',
    ]);

    $otherHolding = CryptoInvestment::factory()->active()->create([
        'user_id' => $otherUser->id,
        'coingecko_asset_id' => 'ethereum',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investments')
        ->assertSuccessful()
        ->assertJsonPath('meta.summary.total', 1)
        ->assertJsonPath('data.0.id', $ownHolding->id);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investments/'.$otherHolding->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Crypto investment not found');
});

it('rejects invest when crypto investment is disabled', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => PlatformSetting::CRYPTO_INVESTMENT_IS_ENABLED],
        ['value' => '0'],
    );

    $user = User::factory()->create();
    $token = cryptoAssetMemberToken($user);
    fundedCryptoAssetMember($user, 10000);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/crypto-investment-assets/bitcoin/invest', [
            'amount_usd' => 500,
            'fee_charge_source' => CryptoInvestmentFeeChargeSource::FromWallet->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crypto_investment']);

    expect(WalletLedgerEntry::query()->count())->toBe(0);
});
