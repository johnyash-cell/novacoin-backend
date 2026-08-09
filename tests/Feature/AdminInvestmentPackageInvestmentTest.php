<?php

use App\Enums\InvestmentStatus;
use App\Models\Admin;
use App\Models\Investment;
use App\Models\InvestmentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function packageInvestmentsAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

it('lists investors for an investment package', function () {
    $package = InvestmentPackage::factory()->open()->create(['name' => 'Growth Pack']);
    $otherPackage = InvestmentPackage::factory()->open()->create();
    $member = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Okeke',
        'email' => 'ada@example.com',
    ]);

    Investment::factory()->active()->create([
        'user_id' => $member->id,
        'investment_package_id' => $package->id,
        'package_name' => $package->name,
        'amount_usd' => 500,
    ]);
    Investment::factory()->active()->create([
        'investment_package_id' => $otherPackage->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.packageInvestmentsAdminToken())
        ->getJson('/api/admin/investment-packages/'.$package->id.'/investments?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment package investments fetched successfully')
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.summary.total', 1)
        ->assertJsonPath('meta.summary.active', 1)
        ->assertJsonPath('data.0.user.email', 'ada@example.com')
        ->assertJsonPath('data.0.amount_usd', 500)
        ->assertJsonPath('data.0.package_name', 'Growth Pack');
});

it('filters package investments by status and search', function () {
    $package = InvestmentPackage::factory()->open()->create();
    $activeUser = User::factory()->create(['email' => 'active-investor@example.com']);
    $endedUser = User::factory()->create(['email' => 'ended-investor@example.com']);

    Investment::factory()->active()->create([
        'user_id' => $activeUser->id,
        'investment_package_id' => $package->id,
    ]);
    Investment::factory()->ended()->create([
        'user_id' => $endedUser->id,
        'investment_package_id' => $package->id,
    ]);

    $token = packageInvestmentsAdminToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/'.$package->id.'/investments?status=active')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.user.email', 'active-investor@example.com')
        ->assertJsonPath('data.0.effective_status', InvestmentStatus::Active->value);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/'.$package->id.'/investments?search=ended-investor')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.user.email', 'ended-investor@example.com');
});

it('returns filter options without a search filter', function () {
    $package = InvestmentPackage::factory()->open()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.packageInvestmentsAdminToken())
        ->getJson('/api/admin/investment-packages/'.$package->id.'/investments/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 1)
        ->assertJsonPath('data.investment_package_id', $package->id);

    $filterKeys = collect($response->json('data.filters'))->pluck('key')->all();

    expect($filterKeys)->toBe(['status'])
        ->and($filterKeys)->not->toContain('search');
});

it('rejects unauthenticated package investment list access', function () {
    $package = InvestmentPackage::factory()->open()->create();

    $this->getJson('/api/admin/investment-packages/'.$package->id.'/investments')->assertUnauthorized();
    $this->getJson('/api/admin/investment-packages/'.$package->id.'/investments/filter-options')->assertUnauthorized();
});

it('returns not found for a missing investment package', function () {
    $this->withHeader('Authorization', 'Bearer '.packageInvestmentsAdminToken())
        ->getJson('/api/admin/investment-packages/999999/investments')
        ->assertNotFound();
});
