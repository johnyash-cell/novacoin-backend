<?php

use App\Enums\WalletDepositStatus;
use App\Enums\WalletLedgerEntryType;
use App\Mail\WalletReviewOutcomeMail;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminNotificationRecipient;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletDeposit;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Http::fake([
        'api.coingecko.com/*' => Http::response([
            'bitcoin' => ['usd' => 50000],
        ], 200),
    ]);
});

function fundingAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

function fundingUserToken(?User $user = null): string
{
    return auth('api')->login($user ?? User::factory()->create());
}

it('rejects unauthenticated wallet access', function () {
    $this->getJson('/api/wallet')->assertUnauthorized();
});

it('returns a zero wallet balance for a new member', function () {
    $token = fundingUserToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/wallet')
        ->assertSuccessful()
        ->assertJsonPath('data.available_balance', 0)
        ->assertJsonPath('data.currency_code', 'USD');

    $this->assertDatabaseCount('user_wallets', 1);
});

it('returns a live deposit quote from CoinGecko', function () {
    $wallet = PlatformCryptoWallet::factory()->create([
        'coingecko_asset_id' => 'bitcoin',
        'is_available_for_funding' => true,
    ]);
    $token = fundingUserToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/wallet/deposit-quote?usd_amount=1000&platform_crypto_wallet_id='.$wallet->id)
        ->assertSuccessful()
        ->assertJsonPath('data.usd_amount', 1000)
        ->assertJsonPath('data.conversion_rate_usd_per_unit', 50000)
        ->assertJsonPath('data.crypto_amount', 0.02)
        ->assertJsonPath('data.wallet_address', $wallet->wallet_address);
});

it('rejects quote for a wallet that is not available for funding', function () {
    $wallet = PlatformCryptoWallet::factory()->unavailableForFunding()->create();
    $token = fundingUserToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/wallet/deposit-quote?usd_amount=1000&platform_crypto_wallet_id='.$wallet->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform_crypto_wallet_id']);
});

it('submits a deposit with proof without crediting balance', function () {
    $wallet = PlatformCryptoWallet::factory()->create([
        'coingecko_asset_id' => 'bitcoin',
    ]);
    $user = User::factory()->create();
    $token = fundingUserToken($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/wallet/deposits', [
            'usd_amount' => 1000,
            'platform_crypto_wallet_id' => $wallet->id,
            'proof_image' => UploadedFile::fake()->image('proof.png'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.usd_amount', 1000)
        ->assertJsonPath('data.status', WalletDepositStatus::PendingApproval->value)
        ->assertJsonPath('data.crypto_amount_expected', 0.02);

    expect($response->json('data.reference_number'))->toMatch('/^WD-\d{8}-[A-Z0-9]{8}$/');
    expect(UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBeNull();
    $this->assertDatabaseCount('wallet_deposits', 1);
    $deposit = WalletDeposit::query()->first();
    expect($deposit->reference_number)->toMatch('/^WD-\d{8}-[A-Z0-9]{8}$/');
    Storage::disk('public')->assertExists($deposit->proof_image_path);
});

it('approves a deposit and credits the usd amount once', function () {
    Mail::fake();
    $user = User::factory()->create();
    $wallet = PlatformCryptoWallet::factory()->create();
    $deposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'platform_crypto_wallet_id' => $wallet->id,
        'usd_amount' => 1000,
        'asset_symbol' => $wallet->asset_symbol,
        'network_name' => $wallet->network_name,
        'wallet_address' => $wallet->wallet_address,
    ]);
    $adminToken = fundingAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WalletDepositStatus::Approved->value);

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(1000.0);
    $this->assertDatabaseHas('wallet_ledger_entries', [
        'entry_type' => WalletLedgerEntryType::DepositCredit->value,
        'wallet_deposit_id' => $deposit->id,
        'amount' => 1000,
    ]);
    Mail::assertQueued(WalletReviewOutcomeMail::class, function (WalletReviewOutcomeMail $mail) use ($user): bool {
        return $mail->hasTo($user->email) && $mail->emailSubject === 'Deposit approved';
    });
    $this->assertDatabaseCount('admin_notifications', 0);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve')
        ->assertSuccessful();

    expect((float) UserWallet::query()->where('user_id', $user->id)->value('available_balance'))->toBe(1000.0);
    expect(WalletLedgerEntry::query()->where('wallet_deposit_id', $deposit->id)->count())->toBe(1);
});

it('skips deposit review email when admin opts out', function () {
    Mail::fake();
    $user = User::factory()->create();
    $deposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 1000,
    ]);
    $adminToken = fundingAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve', [
            'send_email' => false,
        ])
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('notifies the member by email and in-app when admin opts in on deposit approve', function () {
    Mail::fake();
    $user = User::factory()->create();
    $deposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 1000,
    ]);
    $adminToken = fundingAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve', [
            'send_email' => true,
            'send_in_app_notification' => true,
        ])
        ->assertSuccessful();

    Mail::assertQueued(WalletReviewOutcomeMail::class, function (WalletReviewOutcomeMail $mail) use ($user): bool {
        return $mail->hasTo($user->email) && $mail->emailSubject === 'Deposit approved';
    });
    $this->assertDatabaseCount('admin_notifications', 1);
    $this->assertDatabaseHas('admin_notification_recipients', [
        'user_id' => $user->id,
        'admin_notification_id' => AdminNotification::query()->value('id'),
    ]);
    expect(AdminNotificationRecipient::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('declines a deposit without changing balance', function () {
    $user = User::factory()->create();
    $deposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $user->id,
        'usd_amount' => 500,
    ]);
    $adminToken = fundingAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/decline', [
            'decline_reason' => 'Screenshot unclear',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', WalletDepositStatus::Declined->value)
        ->assertJsonPath('data.decline_reason', 'Screenshot unclear');

    expect(UserWallet::query()->where('user_id', $user->id)->exists())->toBeFalse();
    $this->assertDatabaseCount('wallet_ledger_entries', 0);
});

it('lists only the authenticated users deposits', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    WalletDeposit::factory()->create(['user_id' => $user->id, 'usd_amount' => 100]);
    WalletDeposit::factory()->create(['user_id' => $otherUser->id, 'usd_amount' => 999]);

    $token = fundingUserToken($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/wallet/deposits')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.usd_amount', 100)
        ->assertJsonStructure(['data' => [['reference_number']]]);
});

it('lets admins list pending deposits', function () {
    WalletDeposit::factory()->pendingApproval()->create();
    WalletDeposit::factory()->approved()->create();
    $adminToken = fundingAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/wallet-deposits?status=pending_approval')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.status', WalletDepositStatus::PendingApproval->value);
});

it('validates proof image type on deposit submit', function () {
    $wallet = PlatformCryptoWallet::factory()->create();
    $token = fundingUserToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/wallet/deposits', [
            'usd_amount' => 100,
            'platform_crypto_wallet_id' => $wallet->id,
            'proof_image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable();
});
