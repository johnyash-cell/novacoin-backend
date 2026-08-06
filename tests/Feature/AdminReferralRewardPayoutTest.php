<?php

use App\Models\Admin;
use App\Models\ReferralRewardPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function referralPayoutAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

it('rejects unauthenticated referral payout list access', function () {
    $this->getJson('/api/admin/referral-reward-payouts')->assertUnauthorized();
    $this->getJson('/api/admin/referral-reward-payouts/filter-options')->assertUnauthorized();
});

it('returns filter options without a search filter', function () {
    $token = referralPayoutAdminToken();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/referral-reward-payouts/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 1);

    $filterKeys = collect($response->json('data.filters'))->pluck('key')->all();

    expect($filterKeys)->toContain('date_range')
        ->and($filterKeys)->not->toContain('search');
});

it('lists referral reward payouts with pagination', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create([
        'referred_by_user_id' => $referrer->id,
    ]);
    $payout = ReferralRewardPayout::factory()->create([
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'amount' => 10,
    ]);
    $token = referralPayoutAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/referral-reward-payouts?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $payout->id)
        ->assertJsonPath('data.0.amount', '10.00')
        ->assertJsonPath('data.0.referrer_user.id', $referrer->id)
        ->assertJsonPath('data.0.referred_user.id', $referred->id)
        ->assertJsonPath('meta.pagination.total', 1);
});

it('searches referral reward payouts by referrer email', function () {
    $referrer = User::factory()->create([
        'email' => 'top-referrer@example.com',
    ]);
    $otherReferrer = User::factory()->create([
        'email' => 'other@example.com',
    ]);
    ReferralRewardPayout::factory()->create([
        'referrer_user_id' => $referrer->id,
    ]);
    ReferralRewardPayout::factory()->create([
        'referrer_user_id' => $otherReferrer->id,
    ]);
    $token = referralPayoutAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/referral-reward-payouts?search=top-referrer')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.referrer_user.email', 'top-referrer@example.com');
});
