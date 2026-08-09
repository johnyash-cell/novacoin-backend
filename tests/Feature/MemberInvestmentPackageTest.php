<?php

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Investment;
use App\Models\InvestmentPackage;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function memberToken(?User $user = null): string
{
    return auth('api')->login($user ?? User::factory()->create());
}

function joinablePackage(array $overrides = []): InvestmentPackage
{
    return InvestmentPackage::factory()->open()->create(array_merge([
        'minimum_amount_usd' => 100,
        'maximum_amount_usd' => 5000,
        'max_participants' => 50,
        'joined_count' => 10,
        'expected_return_percent' => 20,
        'term_days' => 90,
    ], $overrides));
}

function fundedMember(User $user, float $balance = 10000): UserWallet
{
    return UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => $balance,
    ]);
}

it('rejects unauthenticated member investment package requests', function () {
    $package = joinablePackage();

    $this->getJson('/api/investment-packages')->assertUnauthorized();
    $this->getJson('/api/investment-packages/'.$package->id)->assertUnauthorized();
    $this->postJson('/api/investment-packages/'.$package->id.'/invest', [
        'amount_usd' => 500,
    ])->assertUnauthorized();
    $this->getJson('/api/investments')->assertUnauthorized();
});

it('lists investment packages for members including expired packages', function () {
    $token = memberToken();
    $openPackage = joinablePackage(['name' => 'Open Growth', 'is_featured' => true]);
    $expiredPackage = InvestmentPackage::factory()->expired()->create(['name' => 'Old Plan']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investment-packages')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment packages fetched successfully')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $openPackage->id)
        ->assertJsonPath('data.0.can_invest', true)
        ->assertJsonPath('meta.summary.joinable', 1)
        ->assertJsonPath('meta.summary.total', 2);

    $expiredPayload = collect($response->json('data'))->firstWhere('id', $expiredPackage->id);

    expect($expiredPayload['effective_availability_status'])->toBe(InvestmentPackageAvailabilityStatus::Expired->value)
        ->and($expiredPayload['can_invest'])->toBeFalse();
});

it('shows a single investment package for members', function () {
    $token = memberToken();
    $package = joinablePackage([
        'name' => 'Steady 30',
        'risk_level' => InvestmentPackageRiskLevel::Conservative->value,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investment-packages/'.$package->id)
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Steady 30')
        ->assertJsonPath('data.can_invest', true)
        ->assertJsonPath('data.remaining_seats', 40);
});

it('places an investment by debiting wallet balance', function () {
    $user = User::factory()->create();
    $token = memberToken($user);
    fundedMember($user, 5000);
    $package = joinablePackage(['joined_count' => 5]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/investment-packages/'.$package->id.'/invest', [
            'amount_usd' => 1000,
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Investment placed successfully')
        ->assertJsonPath('data.amount_usd', 1000)
        ->assertJsonPath('data.expected_return_amount_usd', 200)
        ->assertJsonPath('data.expected_payout_amount_usd', 1200)
        ->assertJsonPath('data.effective_status', InvestmentStatus::Active->value)
        ->assertJsonPath('data.package_name', $package->name);

    expect(UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(4000.0)
        ->and($package->fresh()->joined_count)->toBe(6);

    $investmentId = Investment::query()->value('id');

    $this->assertDatabaseHas('wallet_ledger_entries', [
        'investment_id' => $investmentId,
        'entry_type' => WalletLedgerEntryType::InvestmentDebit->value,
        'amount' => -1000,
        'balance_after' => 4000,
    ]);
});

it('rejects investment when wallet balance is insufficient', function () {
    $user = User::factory()->create();
    $token = memberToken($user);
    fundedMember($user, 100);
    $package = joinablePackage();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/investment-packages/'.$package->id.'/invest', [
            'amount_usd' => 500,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_usd']);

    expect(Investment::query()->count())->toBe(0)
        ->and(WalletLedgerEntry::query()->count())->toBe(0);
});

it('rejects investment for unavailable packages', function () {
    $user = User::factory()->create();
    $token = memberToken($user);
    fundedMember($user, 5000);
    $package = InvestmentPackage::factory()->expired()->create([
        'minimum_amount_usd' => 100,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/investment-packages/'.$package->id.'/invest', [
            'amount_usd' => 500,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['investment_package_id']);
});

it('rejects investment amounts outside package limits', function () {
    $user = User::factory()->create();
    $token = memberToken($user);
    fundedMember($user, 10000);
    $package = joinablePackage([
        'minimum_amount_usd' => 500,
        'maximum_amount_usd' => 2000,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/investment-packages/'.$package->id.'/invest', [
            'amount_usd' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_usd']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/investment-packages/'.$package->id.'/invest', [
            'amount_usd' => 3000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_usd']);
});

it('lists member investments by active and ended status', function () {
    $user = User::factory()->create();
    $token = memberToken($user);

    Investment::factory()->active()->create([
        'user_id' => $user->id,
        'package_name' => 'Active Plan',
    ]);
    Investment::factory()->ended()->create([
        'user_id' => $user->id,
        'package_name' => 'Ended Plan',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investments?status=active')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.package_name', 'Active Plan')
        ->assertJsonPath('meta.summary.active', 1)
        ->assertJsonPath('meta.summary.ended', 1);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/investments?status=ended')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.package_name', 'Ended Plan')
        ->assertJsonPath('data.0.effective_status', InvestmentStatus::Ended->value);
});

it('shows a single investment only for the owning member', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $investment = Investment::factory()->active()->create([
        'user_id' => $owner->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.memberToken($owner))
        ->getJson('/api/investments/'.$investment->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $investment->id);

    $this->withHeader('Authorization', 'Bearer '.memberToken($other))
        ->getJson('/api/investments/'.$investment->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Investment not found');
});

it('ends due investments via scheduled command', function () {
    $startedAt = now()->subDays(3)->subMinute();
    $dueInvestment = Investment::factory()->create([
        'term_days' => 3,
        'amount_usd' => 100,
        'expected_return_amount_usd' => 10,
        'expected_payout_amount_usd' => 110,
        'accrued_return_usd' => 0,
        'status' => InvestmentStatus::Active->value,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(3),
        'ended_at' => null,
        'payout_completed_at' => null,
    ]);

    Artisan::call('investments:end-due');

    expect($dueInvestment->fresh())
        ->status->toBe(InvestmentStatus::Ended->value)
        ->ended_at->not->toBeNull()
        ->payout_completed_at->not->toBeNull();
});
