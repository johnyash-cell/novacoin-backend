<?php

use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Investment;
use App\Models\InvestmentDailyEarningLog;
use App\Models\InvestmentPackage;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\Investment\AccruesFlatDailyReturnForInvestment;
use App\Services\Investment\ProcessesInvestmentDailyAccrualAndMaturityPayouts;
use App\Services\Investment\SettlesMaturedInvestmentPayoutToUserWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function dailyEarningMemberToken(?User $user = null): string
{
    return auth('api')->login($user ?? User::factory()->create());
}

function dailyEarningFundedWallet(User $user, float $balance = 0): UserWallet
{
    return UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => $balance,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function activeInvestmentWithTerm(User $user, int $termDays, float $expectedReturn, array $overrides = []): Investment
{
    $startedAt = now()->subDays(min(2, max(0, $termDays - 1)))->startOfDay()->addHours(10);

    return Investment::factory()->create(array_merge([
        'user_id' => $user->id,
        'investment_package_id' => InvestmentPackage::factory()->open()->create()->id,
        'amount_usd' => 1000,
        'expected_return_percent' => 20,
        'term_days' => $termDays,
        'expected_return_amount_usd' => $expectedReturn,
        'expected_payout_amount_usd' => round(1000 + $expectedReturn, 2),
        'accrued_return_usd' => 0,
        'status' => InvestmentStatus::Active->value,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays($termDays),
        'ended_at' => null,
        'payout_completed_at' => null,
    ], $overrides));
}

it('does not accrue on the subscribe calendar day', function () {
    $user = User::factory()->create();
    $startedAt = now()->startOfDay()->addHours(8);
    $investment = activeInvestmentWithTerm($user, 5, 50.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    $created = app(AccruesFlatDailyReturnForInvestment::class)->accrue($investment);

    expect($created)->toBe(0)
        ->and(InvestmentDailyEarningLog::query()->where('investment_id', $investment->id)->count())->toBe(0)
        ->and((float) $investment->fresh()->accrued_return_usd)->toBe(0.0);
});

it('accrues the same flat amount each day and puts leftover cents on the last day', function () {
    $user = User::factory()->create();
    dailyEarningFundedWallet($user, 0);

    // start T-3 → earnings T-2, T-1, T (next-day rule, 3 days)
    $startedAt = now()->subDays(3)->startOfDay()->addHours(9);
    $investment = activeInvestmentWithTerm($user, 3, 100.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(3),
    ]);

    $created = app(AccruesFlatDailyReturnForInvestment::class)->accrue($investment);

    expect($created)->toBe(3);

    $logs = InvestmentDailyEarningLog::query()
        ->where('investment_id', $investment->id)
        ->orderBy('earning_date')
        ->get();

    expect($logs)->toHaveCount(3)
        ->and($logs[0]->earning_date->toDateString())->toBe($startedAt->copy()->addDay()->toDateString())
        ->and((float) $logs[0]->amount_usd)->toBe(33.33)
        ->and((float) $logs[1]->amount_usd)->toBe(33.33)
        ->and((float) $logs[2]->amount_usd)->toBe(33.34)
        ->and((float) $logs->sum('amount_usd'))->toBe(100.0)
        ->and((float) $investment->fresh()->accrued_return_usd)->toBe(100.0)
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(0.0);
});

it('does not double-accrue the same earning date', function () {
    $user = User::factory()->create();
    $startedAt = now()->subDay()->startOfDay()->addHours(8);
    $investment = activeInvestmentWithTerm($user, 5, 50.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    $service = app(AccruesFlatDailyReturnForInvestment::class);

    expect($service->accrue($investment))->toBe(1)
        ->and($service->accrue($investment->fresh()))->toBe(0)
        ->and(InvestmentDailyEarningLog::query()->where('investment_id', $investment->id)->count())->toBe(1)
        ->and((float) $investment->fresh()->accrued_return_usd)->toBe(10.0);
});

it('catch-up accrues missed days without changing the spendable wallet', function () {
    $user = User::factory()->create();
    dailyEarningFundedWallet($user, 250);

    // start T-4 → earnings T-3..T (4 days under next-day rule)
    $startedAt = now()->subDays(4)->startOfDay()->addHours(12);
    $investment = activeInvestmentWithTerm($user, 10, 200.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(10),
    ]);

    app(AccruesFlatDailyReturnForInvestment::class)->accrue($investment);

    expect(InvestmentDailyEarningLog::query()->where('investment_id', $investment->id)->count())->toBe(4)
        ->and((float) $investment->fresh()->accrued_return_usd)->toBe(80.0)
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(250.0);
});

it('removes a legacy same-day subscribe earning before accruing next-day slices', function () {
    $user = User::factory()->create();
    $startedAt = now()->subDays(2)->startOfDay()->addHours(10);
    $investment = activeInvestmentWithTerm($user, 5, 50.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
        'accrued_return_usd' => 10,
    ]);

    InvestmentDailyEarningLog::factory()->create([
        'investment_id' => $investment->id,
        'earning_date' => $startedAt->toDateString(),
        'amount_usd' => 10,
        'accrued_return_after_usd' => 10,
    ]);

    app(AccruesFlatDailyReturnForInvestment::class)->accrue($investment);

    expect(InvestmentDailyEarningLog::query()
        ->where('investment_id', $investment->id)
        ->whereDate('earning_date', $startedAt->toDateString())
        ->exists())->toBeFalse()
        ->and(InvestmentDailyEarningLog::query()->where('investment_id', $investment->id)->count())->toBe(2)
        ->and((float) $investment->fresh()->accrued_return_usd)->toBe(20.0);
});

it('settles principal plus accrued return to the wallet once when matured', function () {
    $user = User::factory()->create();
    dailyEarningFundedWallet($user, 50);

    $startedAt = now()->subDays(3)->subMinute();
    $investment = activeInvestmentWithTerm($user, 3, 100.00, [
        'amount_usd' => 1000,
        'expected_payout_amount_usd' => 1100,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(3),
    ]);

    $settled = app(SettlesMaturedInvestmentPayoutToUserWallet::class)->settleIfDue($investment);

    expect($settled)->toBeTrue();

    $investment->refresh();

    expect($investment->status)->toBe(InvestmentStatus::Ended->value)
        ->and($investment->payout_completed_at)->not->toBeNull()
        ->and((float) $investment->accrued_return_usd)->toBe(100.0)
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(1150.0)
        ->and(InvestmentDailyEarningLog::query()->where('investment_id', $investment->id)->count())->toBe(3);

    $this->assertDatabaseHas('wallet_ledger_entries', [
        'investment_id' => $investment->id,
        'entry_type' => WalletLedgerEntryType::InvestmentPayoutCredit->value,
        'amount' => 1100,
        'balance_after' => 1150,
    ]);

    expect(app(SettlesMaturedInvestmentPayoutToUserWallet::class)->settleIfDue($investment->fresh()))->toBeFalse()
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(1150.0);
});

it('ends due investments via scheduled command with wallet payout', function () {
    $user = User::factory()->create();
    dailyEarningFundedWallet($user, 0);

    $startedAt = now()->subDays(2)->subMinute();
    $investment = activeInvestmentWithTerm($user, 2, 40.00, [
        'amount_usd' => 500,
        'expected_payout_amount_usd' => 540,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(2),
    ]);

    Artisan::call('investments:end-due');

    $investment->refresh();

    expect($investment->status)->toBe(InvestmentStatus::Ended->value)
        ->and($investment->payout_completed_at)->not->toBeNull()
        ->and((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(540.0);
});

it('exposes zero escrow on invest day and earns from the next day', function () {
    $user = User::factory()->create();
    $token = dailyEarningMemberToken($user);
    $startedAt = now()->startOfDay()->addHours(7);
    $investment = activeInvestmentWithTerm($user, 4, 40.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(4),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investments/'.$investment->id)
        ->assertSuccessful()
        ->assertJsonPath('data.accrued_return_usd', '0.00')
        ->assertJsonPath('data.today_earning_usd', '0.00')
        ->assertJsonPath('data.total_earned_return_usd', '0.00')
        ->assertJsonPath('data.payout_completed_at', null);

    $startedYesterday = now()->subDay()->startOfDay()->addHours(7);
    $nextDayInvestment = activeInvestmentWithTerm($user, 4, 40.00, [
        'started_at' => $startedYesterday,
        'matures_at' => $startedYesterday->copy()->addDays(4),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investments/'.$nextDayInvestment->id)
        ->assertSuccessful()
        ->assertJsonPath('data.accrued_return_usd', '10.00')
        ->assertJsonPath('data.today_earning_usd', '10.00')
        ->assertJsonPath('data.total_earned_return_usd', '10.00');
});

it('lists daily earnings for the owning member only', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = dailyEarningMemberToken($owner);

    // start T-2 → earnings T-1, T (2 days)
    $startedAt = now()->subDays(2)->startOfDay()->addHours(6);
    $investment = activeInvestmentWithTerm($owner, 5, 50.00, [
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(5),
    ]);

    app(ProcessesInvestmentDailyAccrualAndMaturityPayouts::class)->processInvestment($investment);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investments/'.$investment->id.'/daily-earnings?page=1&per_page=10&sort_by=oldest')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment daily earnings fetched successfully')
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.amount_usd', '10.00')
        ->assertJsonPath('meta.filters.sort_by', 'oldest');

    $this->withHeader('Authorization', 'Bearer '.dailyEarningMemberToken($other))
        ->getJson('/api/investments/'.$investment->id.'/daily-earnings')
        ->assertNotFound()
        ->assertJsonPath('message', 'Investment not found');
});
