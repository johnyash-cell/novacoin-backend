<?php

use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Admin;
use App\Models\CryptoInvestment;
use App\Models\CryptoInvestmentDailyValuation;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\CryptoInvestment\MarksCryptoInvestmentToMarketForDay;
use App\Services\CryptoInvestment\SettlesMaturedCryptoInvestmentPayoutToUserWallet;
use App\Services\Wallet\FetchesCoinGeckoUsdAssetPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function cryptoMtmFundedWallet(User $user, float $balance = 0): UserWallet
{
    return UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => $balance,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function activeCryptoHolding(User $user, int $termDays = 3, array $overrides = []): CryptoInvestment
{
    $startedAt = now()->subDays(min(2, max(0, $termDays - 1)))->startOfDay()->addHours(10);
    $committedUsd = 5000.0;
    $entryPriceUsd = 50000.0;

    return CryptoInvestment::factory()->create(array_merge([
        'user_id' => $user->id,
        'coingecko_asset_id' => 'bitcoin',
        'asset_symbol' => 'BTC',
        'asset_label' => 'Bitcoin',
        'amount_usd' => $committedUsd,
        'committed_usd' => $committedUsd,
        'fee_usd' => 50,
        'entry_price_usd' => $entryPriceUsd,
        'units' => 0.1,
        'current_escrow_usd' => $committedUsd,
        'last_price_usd' => $entryPriceUsd,
        'max_loss_enabled' => true,
        'max_loss_floor_usd' => 2500,
        'term_days' => $termDays,
        'status' => InvestmentStatus::Active->value,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays($termDays),
        'ended_at' => null,
        'payout_completed_at' => null,
    ], $overrides));
}

function fakeCoinUsdPrice(float $usdPrice): void
{
    app()->instance(FetchesCoinGeckoUsdAssetPrice::class, new class($usdPrice) extends FetchesCoinGeckoUsdAssetPrice
    {
        public function __construct(private float $usdPrice) {}

        public function fetchUsdPricePerUnit(string $coingeckoAssetId): float
        {
            return $this->usdPrice;
        }
    });
}

it('does not mark to market on the subscribe calendar day', function () {
    fakeCoinUsdPrice(55000);

    $user = User::factory()->create();
    $startedAt = now()->startOfDay()->addHours(8);
    $holding = activeCryptoHolding($user, 5, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    $created = app(MarksCryptoInvestmentToMarketForDay::class)->mark($holding);

    expect($created)->toBe(0)
        ->and(CryptoInvestmentDailyValuation::query()->where('crypto_investment_id', $holding->id)->count())->toBe(0)
        ->and((float) $holding->fresh()->current_escrow_usd)->toBe(5000.0);
});

it('marks escrow up when price rises and does not touch spendable wallet', function () {
    fakeCoinUsdPrice(55000);

    $user = User::factory()->create();
    cryptoMtmFundedWallet($user, 250);

    $startedAt = now()->subDays(2)->startOfDay()->addHours(9);
    $holding = activeCryptoHolding($user, 5, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    $created = app(MarksCryptoInvestmentToMarketForDay::class)->mark($holding);

    expect($created)->toBe(2)
        ->and((float) $holding->fresh()->current_escrow_usd)->toBe(5500.0)
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(250.0);
});

it('clamps escrow to max loss floor when price crashes', function () {
    fakeCoinUsdPrice(20000);

    $user = User::factory()->create();
    $startedAt = now()->subDay()->startOfDay()->addHours(8);
    $holding = activeCryptoHolding($user, 5, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    app(MarksCryptoInvestmentToMarketForDay::class)->mark($holding);

    $holding->refresh();
    $valuation = CryptoInvestmentDailyValuation::query()
        ->where('crypto_investment_id', $holding->id)
        ->first();

    expect((float) $holding->current_escrow_usd)->toBe(2500.0)
        ->and($valuation->was_clamped_by_max_loss)->toBeTrue();
});

it('settles current escrow to wallet once when matured', function () {
    fakeCoinUsdPrice(55000);

    $user = User::factory()->create();
    cryptoMtmFundedWallet($user, 50);

    $startedAt = now()->subDays(3)->subMinute();
    $holding = activeCryptoHolding($user, 3, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(3),
        'current_escrow_usd' => 5000,
    ]);

    $settled = app(SettlesMaturedCryptoInvestmentPayoutToUserWallet::class)->settleIfDue($holding);

    expect($settled)->toBeTrue();

    $holding->refresh();

    expect($holding->status)->toBe(InvestmentStatus::Ended->value)
        ->and($holding->payout_completed_at)->not->toBeNull()
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(5550.0);

    $this->assertDatabaseHas('wallet_ledger_entries', [
        'crypto_investment_id' => $holding->id,
        'entry_type' => WalletLedgerEntryType::CryptoInvestmentPayoutCredit->value,
        'amount' => 5500,
    ]);

    expect(app(SettlesMaturedCryptoInvestmentPayoutToUserWallet::class)->settleIfDue($holding->fresh()))->toBeFalse();
});

it('settles matured holdings via scheduled command', function () {
    fakeCoinUsdPrice(55000);

    $user = User::factory()->create();
    cryptoMtmFundedWallet($user, 0);

    $startedAt = now()->subDays(2)->subMinute();
    $holding = activeCryptoHolding($user, 2, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(2),
    ]);

    Artisan::call('crypto-investments:mark-to-market-and-settle');

    $holding->refresh();

    expect($holding->status)->toBe(InvestmentStatus::Ended->value)
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(5500.0);
});

it('lists all crypto investments for admin', function () {
    fakeCoinUsdPrice(55000);

    $adminToken = auth('admin')->login(Admin::factory()->create());
    $user = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $startedAt = now()->subDay()->startOfDay()->addHours(8);
    $holding = activeCryptoHolding($user, 5, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/crypto-investments')
        ->assertSuccessful()
        ->assertJsonPath('meta.summary.total', 1)
        ->assertJsonPath('data.0.id', $holding->id)
        ->assertJsonPath('data.0.user.email', 'ada@example.com')
        ->assertJsonPath('data.0.current_escrow_usd', 5500);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/crypto-investments/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 2);
});
