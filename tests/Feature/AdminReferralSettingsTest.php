<?php

use App\Enums\ReferralRewardPayoutMode;
use App\Models\Admin;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function referralSettingsAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

it('rejects unauthenticated referral settings access', function () {
    $this->getJson('/api/admin/referral-settings')->assertUnauthorized();
    $this->putJson('/api/admin/referral-settings', [
        'reward_amount_usd' => 15,
    ])->assertUnauthorized();
});

it('returns the default referral settings', function () {
    $token = referralSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/referral-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.reward_amount_usd', '10.00')
        ->assertJsonPath('data.payout_mode', ReferralRewardPayoutMode::FirstApprovedDepositOnly->value)
        ->assertJsonPath('data.payout_mode_label', 'First approved deposit only')
        ->assertJsonPath('data.allowed_payout_modes.0.value', ReferralRewardPayoutMode::FirstApprovedDepositOnly->value)
        ->assertJsonPath('data.allowed_payout_modes.1.value', ReferralRewardPayoutMode::EveryApprovedDeposit->value);
});

it('updates reward amount and payout mode', function () {
    $token = referralSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/referral-settings', [
            'reward_amount_usd' => 15.5,
            'payout_mode' => ReferralRewardPayoutMode::EveryApprovedDeposit->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.reward_amount_usd', '15.50')
        ->assertJsonPath('data.payout_mode', ReferralRewardPayoutMode::EveryApprovedDeposit->value);

    $this->assertDatabaseHas('platform_settings', [
        'key' => PlatformSetting::REFERRAL_REWARD_AMOUNT_USD,
        'value' => '15.50',
    ]);
    $this->assertDatabaseHas('platform_settings', [
        'key' => PlatformSetting::REFERRAL_REWARD_PAYOUT_MODE,
        'value' => ReferralRewardPayoutMode::EveryApprovedDeposit->value,
    ]);
});

it('requires at least one settings field on update', function () {
    $token = referralSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/referral-settings', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward_amount_usd']);
});

it('rejects invalid payout mode values', function () {
    $token = referralSettingsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/referral-settings', [
            'payout_mode' => 'not_a_real_mode',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payout_mode']);
});
