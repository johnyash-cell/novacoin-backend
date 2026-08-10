<?php

use App\Enums\CryptoInvestmentFeeType;
use App\Models\User;
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
        ], 200),
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'bitcoin' => ['usd' => 50000],
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
        'supported_asset_ids' => ['bitcoin'],
    ]);
});

function priceHistoryMemberToken(?User $user = null): string
{
    return auth('api')->login($user ?? User::factory()->create());
}

it('rejects unauthenticated price history access', function () {
    $this->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=7d')
        ->assertUnauthorized();
});

it('returns cached price history points oldest to newest for a supported coin', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
            'prices' => [
                [1_720_000_000_000, 64000.5],
                [1_720_086_400_000, 64880.0],
                [1_720_172_800_000, 65100.25],
            ],
        ], 200),
    ]);

    $token = priceHistoryMemberToken();

    $first = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=7d')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Crypto asset price history fetched successfully')
        ->assertJsonPath('data.coingecko_asset_id', 'bitcoin')
        ->assertJsonPath('data.asset_symbol', 'BTC')
        ->assertJsonPath('data.asset_label', 'Bitcoin')
        ->assertJsonPath('data.range', '7d')
        ->assertJsonPath('data.currency', 'usd');

    expect($first->json('data.points'))->toHaveCount(3)
        ->and($first->json('data.points.0.t'))->toEndWith('Z')
        ->and((float) $first->json('data.points.0.price_usd'))->toBe(64000.5)
        ->and((float) $first->json('data.points.1.price_usd'))->toBe(64880.0)
        ->and((float) $first->json('data.points.2.price_usd'))->toBe(65100.25);

    // Second request should hit cache — still one outbound market_chart call.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=7d')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data.points');

    Http::assertSentCount(1);
});

it('defaults range to 7d when omitted', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
            'prices' => [
                [1_720_000_000_000, 64000],
            ],
        ], 200),
    ]);

    $token = priceHistoryMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history')
        ->assertSuccessful()
        ->assertJsonPath('data.range', '7d');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/coins/bitcoin/market_chart')
            && ($request['days'] ?? null) == 7;
    });
});

it('rejects unsupported coins with validation on coingecko_asset_id', function () {
    $token = priceHistoryMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/dogecoin/price-history?range=24h')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coingecko_asset_id'])
        ->assertJsonPath('message', 'This crypto asset is not enabled for investment.');
});

it('rejects an invalid range', function () {
    $token = priceHistoryMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=3h')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['range']);
});

it('returns empty points with 200 when CoinGecko misses', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response(['error' => 'busy'], 503),
    ]);

    $token = priceHistoryMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=24h')
        ->assertSuccessful()
        ->assertJsonPath('data.range', '24h')
        ->assertJsonPath('data.points', []);
});

it('still returns price history when crypto investing is disabled', function () {
    app(UpdatesCryptoInvestmentProgramSettings::class)->update([
        'is_enabled' => false,
        'term_days' => 30,
        'minimum_amount_usd' => 100,
        'maximum_amount_usd' => 10000,
        'fee_type' => CryptoInvestmentFeeType::FixedUsd->value,
        'fee_value' => 50,
        'max_loss_enabled' => true,
        'max_loss_percent' => 50,
        'supported_asset_ids' => ['bitcoin'],
    ]);

    Http::fake([
        'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
            'prices' => [
                [1_720_000_000_000, 64000],
            ],
        ], 200),
    ]);

    $token = priceHistoryMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=30d')
        ->assertSuccessful()
        ->assertJsonPath('data.points.0.price_usd', 64000);
});

it('supports 1y range', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
            'prices' => [
                [1_700_000_000_000, 30000],
                [1_720_000_000_000, 64000],
            ],
        ], 200),
    ]);

    $token = priceHistoryMemberToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/crypto-investment-assets/bitcoin/price-history?range=1y')
        ->assertSuccessful()
        ->assertJsonPath('data.range', '1y');

    Http::assertSent(function ($request): bool {
        return ($request['days'] ?? null) == 365;
    });
});
