<?php

use App\Enums\WalletLedgerEntryType;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function walletBalanceAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

it('sets an absolute wallet balance and returns the admin user profile', function () {
    Log::spy();

    $user = User::factory()->create([
        'first_name' => 'Caesar',
        'last_name' => 'Chapman',
    ]);
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 214.00,
        'currency_code' => 'USD',
    ]);
    $adminToken = walletBalanceAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/admin/users/'.$user->id.'/wallet', [
            'available_balance' => 1500.5,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Wallet balance updated successfully')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.first_name', 'Caesar')
        ->assertJsonPath('data.wallet.available_balance', 1500.5)
        ->assertJsonPath('data.wallet.currency_code', 'USD');

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))
        ->toBe(1500.5);

    $ledger = WalletLedgerEntry::query()->first();

    expect($ledger)->not->toBeNull()
        ->and($ledger->entry_type)->toBe(WalletLedgerEntryType::AdminBalanceAdjustment->value)
        ->and((float) $ledger->amount)->toBe(1286.5)
        ->and((float) $ledger->balance_after)->toBe(1500.5)
        ->and($ledger->created_by_admin_id)->not->toBeNull()
        ->and($ledger->description)->toContain('214.00')
        ->and($ledger->description)->toContain('1500.50');

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/users/'.$user->id)
        ->assertSuccessful()
        ->assertJsonPath('data.wallet.available_balance', 1500.5);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($user): bool {
            return $message === 'Admin set member wallet available balance'
                && ($context['user_id'] ?? null) === $user->id
                && ($context['previous_available_balance'] ?? null) === 214.0
                && ($context['current_available_balance'] ?? null) === 1500.5;
        })
        ->once();
});

it('creates a wallet row when the member has none yet', function () {
    $user = User::factory()->create();
    $adminToken = walletBalanceAdminToken();

    expect(UserWallet::query()->where('user_id', $user->id)->exists())->toBeFalse();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/admin/users/'.$user->id.'/wallet', [
            'available_balance' => 100,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.wallet.available_balance', 100);

    $this->assertDatabaseHas('user_wallets', [
        'user_id' => $user->id,
        'available_balance' => 100,
        'currency_code' => 'USD',
    ]);
});

it('does not write a ledger row when the absolute balance is unchanged', function () {
    $user = User::factory()->create();
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 500,
    ]);
    $adminToken = walletBalanceAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/admin/users/'.$user->id.'/wallet', [
            'available_balance' => 500,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.wallet.available_balance', 500);

    $this->assertDatabaseCount('wallet_ledger_entries', 0);
});

it('rejects a negative available balance', function () {
    $user = User::factory()->create();
    $adminToken = walletBalanceAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/admin/users/'.$user->id.'/wallet', [
            'available_balance' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['available_balance']);
});

it('rejects more than two decimal places', function () {
    $user = User::factory()->create();
    $adminToken = walletBalanceAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/admin/users/'.$user->id.'/wallet', [
            'available_balance' => 10.123,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['available_balance']);
});

it('returns not found for an unknown user', function () {
    $adminToken = walletBalanceAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/admin/users/999999/wallet', [
            'available_balance' => 10,
        ])
        ->assertNotFound();
});

it('rejects unauthenticated wallet balance updates', function () {
    $user = User::factory()->create();

    $this->patchJson('/api/admin/users/'.$user->id.'/wallet', [
        'available_balance' => 10,
    ])->assertUnauthorized();
});

it('keeps profile update separate from wallet fields', function () {
    $user = User::factory()->create([
        'first_name' => 'Old',
    ]);
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 50,
    ]);
    $adminToken = walletBalanceAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->putJson('/api/admin/users/'.$user->id, [
            'first_name' => 'New',
            'available_balance' => 9999,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.first_name', 'New');

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))
        ->toBe(50.0);
});
