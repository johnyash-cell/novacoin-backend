<?php

use App\Enums\WalletLedgerEntryType;
use App\Enums\WalletWithdrawalStatus;
use App\Mail\WalletReviewOutcomeMail;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Models\WalletWithdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.coingecko.com/*' => Http::response([
            'bitcoin' => ['usd' => 50000],
        ], 200),
    ]);
});

function withdrawalAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

function withdrawalUserToken(?User $user = null): string
{
    return auth('api')->login($user ?? User::factory()->create());
}

function fundedUserWallet(User $user, float $availableBalance = 1000): UserWallet
{
    return UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => $availableBalance,
    ]);
}

it('rejects unauthenticated withdrawal access', function () {
    $this->getJson('/api/wallet/withdrawals')->assertUnauthorized();
    $this->postJson('/api/wallet/withdrawals', [])->assertUnauthorized();
    $this->getJson('/api/admin/wallet-withdrawals')->assertUnauthorized();
});

it('submits a withdrawal request debiting balance immediately', function () {
    $user = User::factory()->create();
    fundedUserWallet($user, 1000);
    $payoutMethod = PlatformCryptoWallet::factory()->availableForWithdrawal()->create([
        'coingecko_asset_id' => 'bitcoin',
        'asset_symbol' => 'BTC',
        'network_name' => 'Bitcoin',
    ]);
    $token = withdrawalUserToken($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/wallet/withdrawals', [
            'usd_amount' => 500,
            'platform_crypto_wallet_id' => $payoutMethod->id,
            'destination_wallet_address' => 'bc1qmemberpayoutaddress0000001',
        ])
        ->assertCreated()
        ->assertJsonPath('data.usd_amount', 500)
        ->assertJsonPath('data.status', WalletWithdrawalStatus::PendingApproval->value)
        ->assertJsonPath('data.crypto_amount_expected', 0.01)
        ->assertJsonPath('data.destination_wallet_address', 'bc1qmemberpayoutaddress0000001');

    expect($response->json('data.reference_number'))->toMatch('/^WW-\d{8}-[A-Z0-9]{8}$/');
    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(500.0);
    $this->assertDatabaseHas('wallet_ledger_entries', [
        'entry_type' => WalletLedgerEntryType::WithdrawalDebit->value,
        'wallet_withdrawal_id' => $response->json('data.id'),
        'amount' => -500,
    ]);
});

it('rejects withdrawal when balance is insufficient', function () {
    $user = User::factory()->create();
    fundedUserWallet($user, 100);
    $payoutMethod = PlatformCryptoWallet::factory()->availableForWithdrawal()->create([
        'coingecko_asset_id' => 'bitcoin',
    ]);
    $token = withdrawalUserToken($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/wallet/withdrawals', [
            'usd_amount' => 500,
            'platform_crypto_wallet_id' => $payoutMethod->id,
            'destination_wallet_address' => 'bc1qmemberpayoutaddress0000001',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['usd_amount']);

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(100.0);
    $this->assertDatabaseCount('wallet_withdrawals', 0);
});

it('rejects withdrawal when payout method is not available for withdrawal', function () {
    $user = User::factory()->create();
    fundedUserWallet($user, 1000);
    $payoutMethod = PlatformCryptoWallet::factory()->create([
        'is_available_for_withdrawal' => false,
        'coingecko_asset_id' => 'bitcoin',
    ]);
    $token = withdrawalUserToken($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/wallet/withdrawals', [
            'usd_amount' => 500,
            'platform_crypto_wallet_id' => $payoutMethod->id,
            'destination_wallet_address' => 'bc1qmemberpayoutaddress0000001',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform_crypto_wallet_id']);
});

it('lists only the authenticated users withdrawals', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    WalletWithdrawal::factory()->create(['user_id' => $user->id, 'usd_amount' => 100]);
    WalletWithdrawal::factory()->create(['user_id' => $otherUser->id, 'usd_amount' => 999]);
    $token = withdrawalUserToken($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/wallet/withdrawals')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.usd_amount', 100)
        ->assertJsonStructure(['data' => [['reference_number']]]);
});

it('approves a pending withdrawal without changing balance again', function () {
    Mail::fake();
    $user = User::factory()->create();
    fundedUserWallet($user, 500);
    $withdrawal = WalletWithdrawal::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 500,
    ]);
    $adminToken = withdrawalAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/approve', [
            'outbound_transaction_reference' => 'btc-txid-abc123',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', WalletWithdrawalStatus::Approved->value)
        ->assertJsonPath('data.outbound_transaction_reference', 'btc-txid-abc123');

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(500.0);
    Mail::assertQueued(WalletReviewOutcomeMail::class, function (WalletReviewOutcomeMail $mail) use ($user): bool {
        return $mail->hasTo($user->email) && $mail->emailSubject === 'Withdrawal approved';
    });
    $this->assertDatabaseCount('admin_notifications', 0);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WalletWithdrawalStatus::Approved->value);

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(500.0);
});

it('skips withdrawal review email when admin opts out', function () {
    Mail::fake();
    $user = User::factory()->create();
    fundedUserWallet($user, 500);
    $withdrawal = WalletWithdrawal::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 500,
    ]);
    $adminToken = withdrawalAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/approve', [
            'outbound_transaction_reference' => 'btc-txid-opt-out',
            'send_email' => false,
        ])
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('notifies the member by email and in-app when admin opts in on withdrawal decline', function () {
    Mail::fake();
    $user = User::factory()->create();
    fundedUserWallet($user, 500);
    $withdrawal = WalletWithdrawal::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 500,
    ]);
    $adminToken = withdrawalAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/decline', [
            'decline_reason' => 'Destination address invalid',
            'send_email' => true,
            'send_in_app_notification' => true,
        ])
        ->assertSuccessful();

    Mail::assertQueued(WalletReviewOutcomeMail::class, function (WalletReviewOutcomeMail $mail) use ($user): bool {
        return $mail->hasTo($user->email) && $mail->emailSubject === 'Withdrawal declined';
    });
    $this->assertDatabaseCount('admin_notifications', 1);
    $this->assertDatabaseHas('admin_notification_recipients', [
        'user_id' => $user->id,
        'admin_notification_id' => AdminNotification::query()->value('id'),
    ]);
});

it('declines a pending withdrawal and refunds the held balance', function () {
    $user = User::factory()->create();
    fundedUserWallet($user, 500);
    $withdrawal = WalletWithdrawal::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 500,
    ]);
    $adminToken = withdrawalAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/decline', [
            'decline_reason' => 'Destination address invalid',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', WalletWithdrawalStatus::Declined->value)
        ->assertJsonPath('data.decline_reason', 'Destination address invalid');

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(1000.0);
    $this->assertDatabaseHas('wallet_ledger_entries', [
        'entry_type' => WalletLedgerEntryType::WithdrawalRefundCredit->value,
        'wallet_withdrawal_id' => $withdrawal->id,
        'amount' => 500,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/decline')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WalletWithdrawalStatus::Declined->value);

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(1000.0);
    expect(WalletLedgerEntry::query()->where('wallet_withdrawal_id', $withdrawal->id)->where('entry_type', WalletLedgerEntryType::WithdrawalRefundCredit->value)->count())->toBe(1);
});

it('cannot decline an approved withdrawal', function () {
    $user = User::factory()->create();
    fundedUserWallet($user, 500);
    $withdrawal = WalletWithdrawal::factory()->approved()->create([
        'user_id' => $user->id,
        'usd_amount' => 500,
    ]);
    $adminToken = withdrawalAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-withdrawals/'.$withdrawal->id.'/decline', [
            'decline_reason' => 'Too late',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Approved withdrawals cannot be declined.');
});

it('returns member and admin withdrawal filter options', function () {
    $userToken = withdrawalUserToken();
    $adminToken = withdrawalAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$userToken)
        ->getJson('/api/wallet/withdrawals/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.filters.0.key', 'status')
        ->assertJsonPath('data.total_available_filters', 1);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/wallet-withdrawals/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.filters.0.key', 'status');
});
