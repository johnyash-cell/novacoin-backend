<?php

use App\Enums\InvestmentStatus;
use App\Enums\WalletDepositStatus;
use App\Enums\WalletWithdrawalStatus;
use App\Models\Admin;
use App\Models\Investment;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletDeposit;
use App\Models\WalletWithdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function profileActivityAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

it('shows admin user profile with wallet and activity summary cards', function () {
    $user = User::factory()->create();
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 250.5,
        'currency_code' => 'USD',
    ]);

    WalletDeposit::factory()->approved()->create([
        'user_id' => $user->id,
        'usd_amount' => 1000,
    ]);
    WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 400,
    ]);
    WalletWithdrawal::factory()->approved()->create([
        'user_id' => $user->id,
        'usd_amount' => 200,
    ]);
    WalletWithdrawal::factory()->declined()->create([
        'user_id' => $user->id,
        'usd_amount' => 50,
    ]);
    Investment::factory()->active()->create(['user_id' => $user->id]);
    Investment::factory()->ended()->create(['user_id' => $user->id]);

    $this->withHeader('Authorization', 'Bearer '.profileActivityAdminToken())
        ->getJson('/api/admin/users/'.$user->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.wallet.available_balance', 250.5)
        ->assertJsonPath('data.wallet.currency_code', 'USD')
        ->assertJsonPath('data.total_deposits_usd', 1000)
        ->assertJsonPath('data.total_withdrawals_usd', 200)
        ->assertJsonPath('data.active_investments_count', 1)
        ->assertJsonPath('data.has_google_linked', false);
});

it('lists only the scoped user wallet deposits for admin', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownedDeposit = WalletDeposit::factory()->create([
        'user_id' => $user->id,
        'status' => WalletDepositStatus::PendingApproval->value,
    ]);
    WalletDeposit::factory()->create([
        'user_id' => $otherUser->id,
        'status' => WalletDepositStatus::PendingApproval->value,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.profileActivityAdminToken())
        ->getJson('/api/admin/users/'.$user->id.'/wallet-deposits?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($ownedDeposit->id)
        ->and($response->json('meta.filters.user_id'))->toBe($user->id);
});

it('lists only the scoped user wallet withdrawals for admin', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownedWithdrawal = WalletWithdrawal::factory()->create([
        'user_id' => $user->id,
        'status' => WalletWithdrawalStatus::PendingApproval->value,
    ]);
    WalletWithdrawal::factory()->create([
        'user_id' => $otherUser->id,
        'status' => WalletWithdrawalStatus::PendingApproval->value,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.profileActivityAdminToken())
        ->getJson('/api/admin/users/'.$user->id.'/wallet-withdrawals?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($ownedWithdrawal->id)
        ->and($response->json('meta.filters.user_id'))->toBe($user->id);
});

it('lists only the scoped user investments for admin', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownedInvestment = Investment::factory()->active()->create(['user_id' => $user->id]);
    Investment::factory()->active()->create(['user_id' => $otherUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.profileActivityAdminToken())
        ->getJson('/api/admin/users/'.$user->id.'/investments?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('meta.summary.active', 1)
        ->assertJsonPath('meta.summary.ended', 0);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($ownedInvestment->id)
        ->and($response->json('meta.filters.user_id'))->toBe($user->id);
});

it('returns investment filter options for a user profile tab', function () {
    $user = User::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.profileActivityAdminToken())
        ->getJson('/api/admin/users/'.$user->id.'/investments/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 1)
        ->assertJsonPath('data.filters.0.key', 'status')
        ->assertJsonPath('data.filters.0.options.0.value', InvestmentStatus::Active->value);
});

it('rejects unauthenticated access to user profile activity endpoints', function () {
    $user = User::factory()->create();

    $this->getJson('/api/admin/users/'.$user->id)->assertUnauthorized();
    $this->getJson('/api/admin/users/'.$user->id.'/wallet-deposits')->assertUnauthorized();
    $this->getJson('/api/admin/users/'.$user->id.'/wallet-withdrawals')->assertUnauthorized();
    $this->getJson('/api/admin/users/'.$user->id.'/investments')->assertUnauthorized();
});
