<?php

use App\Contracts\Auth\GoogleIdTokenVerifierContract;
use App\Enums\ReferralRewardPayoutMode;
use App\Enums\WalletLedgerEntryType;
use App\Models\Admin;
use App\Models\PlatformCryptoWallet;
use App\Models\PlatformSetting;
use App\Models\ReferralRewardPayout;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletDeposit;
use App\Services\Auth\VerifiedGoogleUserProfile;
use App\Services\Referral\AttachesReferrerFromReferralCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function referralAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

function referralUserToken(User $user): string
{
    return auth('api')->login($user);
}

it('registers a user with a valid referral code', function () {
    $referrer = User::factory()->create();

    $response = $this->postJson('/api/auth/register', [
        'first_name' => 'Referred',
        'last_name' => 'Member',
        'email' => 'referred@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'referral_code' => $referrer->referral_code,
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.referred_by_user_id', $referrer->id);

    expect($response->json('data.user.referral_code'))->not->toBeEmpty();

    $this->assertDatabaseHas('users', [
        'email' => 'referred@example.com',
        'referred_by_user_id' => $referrer->id,
    ]);
});

it('rejects registration with an invalid referral code', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Referred',
        'last_name' => 'Member',
        'email' => 'bad-code@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'referral_code' => 'NOTEXIST',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referral_code']);

    $this->assertDatabaseMissing('users', [
        'email' => 'bad-code@example.com',
    ]);
});

it('rejects using your own referral code', function () {
    $user = User::factory()->create();

    expect(fn () => app(AttachesReferrerFromReferralCode::class)->attach($user, $user->referral_code))
        ->toThrow(ValidationException::class);
});

it('attaches referral code only when creating a new google user', function () {
    $referrer = User::factory()->create();

    $this->mock(GoogleIdTokenVerifierContract::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->andReturn(new VerifiedGoogleUserProfile(
                googleId: 'google-sub-referral-new',
                email: 'google-referred@example.com',
                firstName: 'Google',
                lastName: 'Referred',
                isEmailVerified: true,
            ));
    });

    $this->postJson('/api/auth/google', [
        'id_token' => 'valid-google-id-token',
        'referral_code' => $referrer->referral_code,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.referred_by_user_id', $referrer->id);

    $existing = User::factory()->create([
        'email' => 'google-existing@example.com',
        'google_id' => null,
        'referred_by_user_id' => null,
    ]);

    $this->mock(GoogleIdTokenVerifierContract::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->andReturn(new VerifiedGoogleUserProfile(
                googleId: 'google-sub-referral-existing',
                email: 'google-existing@example.com',
                firstName: 'Existing',
                lastName: 'User',
                isEmailVerified: true,
            ));
    });

    $this->postJson('/api/auth/google', [
        'id_token' => 'valid-google-id-token',
        'referral_code' => $referrer->referral_code,
    ])->assertSuccessful();

    expect($existing->fresh()->referred_by_user_id)->toBeNull();
});

it('pays the referrer once on first approved deposit by default', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create([
        'referred_by_user_id' => $referrer->id,
    ]);
    $wallet = PlatformCryptoWallet::factory()->create();
    $firstDeposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $referred->id,
        'platform_crypto_wallet_id' => $wallet->id,
        'usd_amount' => 100,
    ]);
    $secondDeposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $referred->id,
        'platform_crypto_wallet_id' => $wallet->id,
        'usd_amount' => 200,
    ]);
    $adminToken = referralAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$firstDeposit->id.'/approve')
        ->assertSuccessful();

    expect((float) UserWallet::query()->where('user_id', $referrer->id)->value('available_balance'))
        ->toBe(10.0);
    $this->assertDatabaseCount('referral_reward_payouts', 1);
    $this->assertDatabaseHas('wallet_ledger_entries', [
        'entry_type' => WalletLedgerEntryType::ReferralCredit->value,
        'wallet_deposit_id' => $firstDeposit->id,
        'amount' => 10,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$firstDeposit->id.'/approve')
        ->assertSuccessful();

    expect((float) UserWallet::query()->where('user_id', $referrer->id)->value('available_balance'))
        ->toBe(10.0);
    $this->assertDatabaseCount('referral_reward_payouts', 1);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$secondDeposit->id.'/approve')
        ->assertSuccessful();

    expect((float) UserWallet::query()->where('user_id', $referrer->id)->value('available_balance'))
        ->toBe(10.0);
    $this->assertDatabaseCount('referral_reward_payouts', 1);
});

it('pays the referrer on every approved deposit when mode is every_approved_deposit', function () {
    PlatformSetting::query()->where('key', PlatformSetting::REFERRAL_REWARD_PAYOUT_MODE)->update([
        'value' => ReferralRewardPayoutMode::EveryApprovedDeposit->value,
    ]);

    $referrer = User::factory()->create();
    $referred = User::factory()->create([
        'referred_by_user_id' => $referrer->id,
    ]);
    $wallet = PlatformCryptoWallet::factory()->create();
    $firstDeposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $referred->id,
        'platform_crypto_wallet_id' => $wallet->id,
        'usd_amount' => 100,
    ]);
    $secondDeposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $referred->id,
        'platform_crypto_wallet_id' => $wallet->id,
        'usd_amount' => 200,
    ]);
    $adminToken = referralAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$firstDeposit->id.'/approve')
        ->assertSuccessful();
    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$secondDeposit->id.'/approve')
        ->assertSuccessful();

    expect((float) UserWallet::query()->where('user_id', $referrer->id)->value('available_balance'))
        ->toBe(20.0);
    expect(ReferralRewardPayout::query()->where('referrer_user_id', $referrer->id)->count())->toBe(2);
});

it('uses the admin-configured reward amount on the next payout', function () {
    PlatformSetting::query()->where('key', PlatformSetting::REFERRAL_REWARD_AMOUNT_USD)->update([
        'value' => '25.50',
    ]);

    $referrer = User::factory()->create();
    $referred = User::factory()->create([
        'referred_by_user_id' => $referrer->id,
    ]);
    $deposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $referred->id,
        'usd_amount' => 100,
    ]);

    $this->withHeader('Authorization', 'Bearer '.referralAdminToken())
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve')
        ->assertSuccessful();

    expect((float) UserWallet::query()->where('user_id', $referrer->id)->value('available_balance'))
        ->toBe(25.5);
});

it('returns the member referral summary and referred users list', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create([
        'referred_by_user_id' => $referrer->id,
    ]);
    ReferralRewardPayout::factory()->create([
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'amount' => 10,
    ]);
    $token = referralUserToken($referrer);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/referral')
        ->assertSuccessful()
        ->assertJsonPath('data.referral_code', $referrer->referral_code)
        ->assertJsonPath('data.referred_users_count', 1)
        ->assertJsonPath('data.total_rewards_earned_usd', '10.00')
        ->assertJsonPath('data.reward_amount_usd', '10.00')
        ->assertJsonPath('data.payout_mode', ReferralRewardPayoutMode::FirstApprovedDepositOnly->value);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/referral/referred-users?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $referred->id)
        ->assertJsonPath('meta.pagination.total', 1);
});

it('rejects unauthenticated referral access', function () {
    $this->getJson('/api/referral')->assertUnauthorized();
    $this->getJson('/api/referral/referred-users')->assertUnauthorized();
});
