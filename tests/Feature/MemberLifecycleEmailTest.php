<?php

use App\Enums\CryptoInvestmentFeeType;
use App\Enums\InvestmentStatus;
use App\Mail\MemberTransactionalMail;
use App\Mail\WalletReviewOutcomeMail;
use App\Models\Admin;
use App\Models\CryptoInvestment;
use App\Models\Investment;
use App\Models\InvestmentPackage;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletDeposit;
use App\Services\CryptoInvestment\SettlesMaturedCryptoInvestmentPayoutToUserWallet;
use App\Services\CryptoInvestment\UpdatesCryptoInvestmentProgramSettings;
use App\Services\Investment\SettlesMaturedInvestmentPayoutToUserWallet;
use App\Services\Wallet\FetchesCoinGeckoUsdAssetPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
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
        'api.coingecko.com/*' => Http::response([
            'bitcoin' => ['usd' => 50000],
        ], 200),
    ]);
});

it('queues a personalized welcome email on register', function () {
    Mail::fake();

    $this->postJson('/api/auth/register', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-welcome@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'phone' => '+15551230001',
    ])->assertCreated();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail): bool {
        return $mail->hasTo('ada-welcome@example.com')
            && str_contains($mail->emailSubject, 'Ada')
            && str_contains($mail->emailBody, 'Hi Ada,')
            && str_contains($mail->emailBody, 'ada-welcome@example.com');
    });
});

it('queues personalized deposit submitted and review outcome emails', function () {
    Mail::fake();
    $user = User::factory()->create([
        'first_name' => 'Nia',
        'last_name' => 'Okoro',
    ]);
    $wallet = PlatformCryptoWallet::factory()->create([
        'coingecko_asset_id' => 'bitcoin',
        'asset_symbol' => 'BTC',
        'network_name' => 'Bitcoin',
        'is_available_for_funding' => true,
    ]);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/wallet/deposits', [
            'usd_amount' => 250,
            'platform_crypto_wallet_id' => $wallet->id,
            'proof_image' => UploadedFile::fake()->image('proof.png'),
        ])
        ->assertCreated();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Nia')
            && str_contains($mail->emailBody, 'Hi Nia,')
            && str_contains($mail->emailBody, 'BTC')
            && str_contains($mail->emailBody, 'Bitcoin')
            && str_contains($mail->emailBody, '$250.00');
    });

    $deposit = WalletDeposit::query()->firstOrFail();
    $adminToken = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve')
        ->assertSuccessful();

    Mail::assertQueued(WalletReviewOutcomeMail::class, function (WalletReviewOutcomeMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Nia')
            && str_contains($mail->emailBody, 'Hi Nia,')
            && str_contains($mail->emailBody, 'approved');
    });
});

it('queues personalized withdrawal submitted email', function () {
    Mail::fake();
    $user = User::factory()->create(['first_name' => 'Kofi']);
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 1000,
    ]);
    $payoutWallet = PlatformCryptoWallet::factory()->create([
        'coingecko_asset_id' => 'bitcoin',
        'is_available_for_withdrawal' => true,
    ]);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/wallet/withdrawals', [
            'usd_amount' => 100,
            'platform_crypto_wallet_id' => $payoutWallet->id,
            'destination_wallet_address' => 'bc1qexamplewithdrawaladdress0001',
        ])
        ->assertCreated();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Kofi')
            && str_contains($mail->emailBody, 'Hi Kofi,')
            && str_contains($mail->emailBody, 'bc1qexamplewithdrawaladdress0001');
    });
});

it('queues personalized fixed investment placed and maturity emails', function () {
    Mail::fake();
    $user = User::factory()->create(['first_name' => 'Maya']);
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 5000,
    ]);
    $package = InvestmentPackage::factory()->open()->create([
        'name' => 'Mail Plan',
        'minimum_amount_usd' => 100,
        'maximum_amount_usd' => 5000,
        'term_days' => 2,
        'expected_return_percent' => 10,
    ]);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/investment-packages/'.$package->id.'/invest', [
            'amount_usd' => 1000,
        ])
        ->assertCreated();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Maya')
            && str_contains($mail->emailSubject, 'Mail Plan')
            && str_contains($mail->emailBody, 'Hi Maya,');
    });

    $startedAt = now()->subDays(2)->subMinute();
    $investment = Investment::factory()->create([
        'user_id' => $user->id,
        'investment_package_id' => $package->id,
        'package_name' => 'Mail Plan',
        'amount_usd' => 1000,
        'expected_return_percent' => 10,
        'term_days' => 2,
        'expected_return_amount_usd' => 100,
        'expected_payout_amount_usd' => 1100,
        'accrued_return_usd' => 0,
        'status' => InvestmentStatus::Active->value,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(2),
        'ended_at' => null,
        'payout_completed_at' => null,
    ]);

    expect(app(SettlesMaturedInvestmentPayoutToUserWallet::class)->settleIfDue($investment))->toBeTrue();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Maya')
            && str_contains($mail->emailBody, 'Principal:')
            && str_contains($mail->emailBody, 'Accrued return:');
    });
});

it('queues personalized crypto investment placed and maturity emails', function () {
    Mail::fake();
    app(UpdatesCryptoInvestmentProgramSettings::class)->update([
        'is_enabled' => true,
        'term_days' => 2,
        'minimum_amount_usd' => 100,
        'maximum_amount_usd' => 10000,
        'fee_type' => CryptoInvestmentFeeType::FixedUsd->value,
        'fee_value' => 50,
        'max_loss_enabled' => false,
        'max_loss_percent' => null,
        'supported_asset_ids' => ['bitcoin'],
    ]);

    $user = User::factory()->create(['first_name' => 'Tunde']);
    UserWallet::factory()->create([
        'user_id' => $user->id,
        'available_balance' => 10000,
    ]);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/crypto-investment-assets/bitcoin/invest', [
            'amount_usd' => 500,
            'fee_charge_source' => 'from_wallet',
        ])
        ->assertCreated();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Tunde')
            && str_contains($mail->emailBody, 'Hi Tunde,')
            && str_contains($mail->emailBody, 'Entry price:');
    });

    app()->instance(FetchesCoinGeckoUsdAssetPrice::class, new class extends FetchesCoinGeckoUsdAssetPrice
    {
        public function __construct() {}

        public function fetchUsdPricePerUnit(string $coingeckoAssetId): float
        {
            return 55000;
        }
    });

    $startedAt = now()->subDays(2)->subMinute();
    $holding = CryptoInvestment::factory()->create([
        'user_id' => $user->id,
        'coingecko_asset_id' => 'bitcoin',
        'asset_symbol' => 'BTC',
        'asset_label' => 'Bitcoin',
        'amount_usd' => 5000,
        'committed_usd' => 5000,
        'fee_usd' => 50,
        'entry_price_usd' => 50000,
        'units' => 0.1,
        'current_escrow_usd' => 5000,
        'last_price_usd' => 50000,
        'max_loss_enabled' => false,
        'term_days' => 2,
        'status' => InvestmentStatus::Active->value,
        'started_at' => $startedAt,
        'matures_at' => $startedAt->copy()->addDays(2),
        'ended_at' => null,
        'payout_completed_at' => null,
    ]);

    expect(app(SettlesMaturedCryptoInvestmentPayoutToUserWallet::class)->settleIfDue($holding))->toBeTrue();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Tunde')
            && str_contains($mail->emailBody, 'Final escrow payout:');
    });
});

it('queues personalized referral reward email when a referred deposit is approved', function () {
    Mail::fake();
    $referrer = User::factory()->create(['first_name' => 'Sam']);
    $referred = User::factory()->create([
        'first_name' => 'Lee',
        'last_name' => 'Chen',
        'referred_by_user_id' => $referrer->id,
    ]);
    $deposit = WalletDeposit::factory()->pendingApproval()->create([
        'user_id' => $referred->id,
        'usd_amount' => 100,
    ]);
    $adminToken = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/wallet-deposits/'.$deposit->id.'/approve')
        ->assertSuccessful();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($referrer, $referred): bool {
        return $mail->hasTo($referrer->email)
            && str_contains($mail->emailSubject, 'Sam')
            && str_contains($mail->emailBody, 'Hi Sam,')
            && str_contains($mail->emailBody, 'Lee Chen')
            && str_contains($mail->emailBody, $referred->email);
    });
});

it('queues personalized ban suspend unsuspend reactivate and promote-to-admin emails', function () {
    Mail::fake();
    $adminToken = auth('admin')->login(Admin::factory()->create());
    $user = User::factory()->create([
        'first_name' => 'Imani',
        'email' => 'lifecycle-member@example.com',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/users/'.$user->id.'/ban', [
            'reason' => 'Policy',
        ])
        ->assertSuccessful();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Imani')
            && str_contains($mail->emailBody, 'Policy')
            && str_contains($mail->emailBody, $user->email);
    });

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/users/'.$user->id.'/reactivate', [
            'reason' => 'Cleared',
        ])
        ->assertSuccessful();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Imani')
            && str_contains($mail->emailBody, 'Cleared');
    });

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/users/'.$user->id.'/suspend', [
            'suspended_until' => now()->addDays(2)->toIso8601String(),
            'reason' => 'Cool off',
        ])
        ->assertSuccessful();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Imani')
            && str_contains($mail->emailBody, 'Cool off');
    });

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/users/'.$user->id.'/unsuspend', [
            'reason' => 'OK',
        ])
        ->assertSuccessful();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->emailSubject, 'Imani')
            && str_contains($mail->emailBody, 'OK');
    });

    $promoteUser = User::factory()->create([
        'first_name' => 'Omar',
        'email' => 'promote-me@example.com',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->postJson('/api/admin/users/'.$promoteUser->id.'/promote-to-admin', [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertCreated();

    Mail::assertQueued(MemberTransactionalMail::class, function (MemberTransactionalMail $mail) use ($promoteUser): bool {
        return $mail->hasTo($promoteUser->email)
            && str_contains($mail->emailSubject, 'Omar')
            && str_contains($mail->emailBody, $promoteUser->email);
    });
});
