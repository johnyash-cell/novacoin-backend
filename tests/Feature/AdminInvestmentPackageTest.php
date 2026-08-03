<?php

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Models\Admin;
use App\Models\InvestmentPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function adminBearerToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

function validInvestmentPackagePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Growth 90',
        'short_pitch' => 'Balanced mid-term package for stronger compounding.',
        'description' => 'A ninety-day package designed for members ready to leave capital at work longer.',
        'expected_return_percent' => 22,
        'term_days' => 90,
        'minimum_amount_usd' => 500,
        'maximum_amount_usd' => 10000,
        'max_participants' => 101,
        'joined_count' => 100,
        'risk_level' => InvestmentPackageRiskLevel::Balanced->value,
        'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
        'expires_at' => null,
        'is_featured' => true,
        'highlights' => [
            'Most popular mid-term plan',
            'Higher return than Steady 30',
            'Clear 90-day maturity',
        ],
    ], $overrides);
}

it('rejects unauthenticated investment package list requests', function () {
    $this->getJson('/api/admin/investment-packages')
        ->assertUnauthorized()
        ->assertJsonPath('status', false);
});

it('creates an investment package', function () {
    $token = adminBearerToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/investment-packages', validInvestmentPackagePayload())
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Investment package created successfully')
        ->assertJsonPath('data.name', 'Growth 90')
        ->assertJsonPath('data.joined_count', 100)
        ->assertJsonPath('data.max_participants', 101)
        ->assertJsonPath('data.remaining_seats', 1)
        ->assertJsonPath('data.risk_level', 'balanced')
        ->assertJsonPath('data.risk_level_label', 'Balanced')
        ->assertJsonPath('data.availability_status', 'open')
        ->assertJsonPath('data.is_featured', true)
        ->assertJsonPath('data.highlights.0', 'Most popular mid-term plan');

    $this->assertDatabaseCount('investment_packages', 1);
});

it('rejects create when open and at capacity', function () {
    $token = adminBearerToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/investment-packages', validInvestmentPackagePayload([
            'max_participants' => 10,
            'joined_count' => 10,
            'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonValidationErrors(['availability_status']);
});

it('rejects create when maximum amount is below minimum', function () {
    $token = adminBearerToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/investment-packages', validInvestmentPackagePayload([
            'minimum_amount_usd' => 500,
            'maximum_amount_usd' => 100,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['maximum_amount_usd']);
});

it('lists investment packages with pagination search filters and summary', function () {
    $token = adminBearerToken();

    InvestmentPackage::factory()->open()->create(['name' => 'Growth Alpha', 'short_pitch' => 'alpha pitch']);
    InvestmentPackage::factory()->limited()->create(['name' => 'Steady Beta']);
    InvestmentPackage::factory()->full()->create(['name' => 'Full Gamma']);
    InvestmentPackage::factory()->expired()->create(['name' => 'Expired Delta']);
    InvestmentPackage::factory()->featured()->create(['name' => 'Featured Epsilon', 'short_pitch' => 'featured pitch']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages?page=1&per_page=2&sort_by=newest&search=Growth')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Investment packages fetched successfully')
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.summary.total', 5)
        ->assertJsonPath('meta.summary.open', 2)
        ->assertJsonPath('meta.summary.limited', 1)
        ->assertJsonPath('meta.summary.full', 1)
        ->assertJsonPath('meta.summary.expired', 1)
        ->assertJsonPath('data.0.name', 'Growth Alpha');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages?availability_status=limited')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.availability_status', 'limited');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages?is_featured=true')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.is_featured', true);
});

it('returns filter options with known catalogs when table is empty', function () {
    $token = adminBearerToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Filter options retrieved successfully')
        ->assertJsonPath('data.total_available_filters', 3)
        ->assertJsonPath('data.filters.0.key', 'availability_status')
        ->assertJsonPath('data.filters.1.key', 'risk_level')
        ->assertJsonPath('data.filters.2.key', 'is_featured')
        ->assertJsonPath('data.filters.0.options.0.value', 'open')
        ->assertJsonPath('data.filters.1.options.0.value', 'conservative');
});

it('shows updates and deletes an investment package', function () {
    $token = adminBearerToken();
    $package = InvestmentPackage::factory()->open()->create([
        'name' => 'Original',
        'joined_count' => 2,
        'max_participants' => 10,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/'.$package->id)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment package fetched successfully')
        ->assertJsonPath('data.id', $package->id)
        ->assertJsonPath('data.name', 'Original');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/investment-packages/'.$package->id, validInvestmentPackagePayload([
            'name' => 'Updated Growth',
            'joined_count' => 3,
            'max_participants' => 12,
            'availability_status' => InvestmentPackageAvailabilityStatus::Limited->value,
            'is_featured' => false,
            'highlights' => null,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment package updated successfully')
        ->assertJsonPath('data.name', 'Updated Growth')
        ->assertJsonPath('data.availability_status', 'limited')
        ->assertJsonPath('data.highlights', []);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/admin/investment-packages/'.$package->id)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment package deleted successfully')
        ->assertJsonPath('data', null);

    $this->assertDatabaseMissing('investment_packages', ['id' => $package->id]);
});

it('patches availability status and featured flag', function () {
    $token = adminBearerToken();
    $package = InvestmentPackage::factory()->open()->create([
        'joined_count' => 1,
        'max_participants' => 10,
        'is_featured' => false,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/admin/investment-packages/'.$package->id.'/availability-status', [
            'availability_status' => InvestmentPackageAvailabilityStatus::Expired->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment package availability updated successfully')
        ->assertJsonPath('data.availability_status', 'expired');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/admin/investment-packages/'.$package->id.'/featured', [
            'is_featured' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Investment package featured flag updated successfully')
        ->assertJsonPath('data.is_featured', true);
});

it('rejects setting open availability when package is at capacity', function () {
    $token = adminBearerToken();
    $package = InvestmentPackage::factory()->atCapacity()->create();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/admin/investment-packages/'.$package->id.'/availability-status', [
            'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['availability_status']);
});

it('expires a due package on show via persist-on-read', function () {
    $token = adminBearerToken();
    $package = InvestmentPackage::factory()->dueToExpire()->create();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/'.$package->id)
        ->assertSuccessful()
        ->assertJsonPath('data.availability_status', 'expired')
        ->assertJsonPath('data.effective_availability_status', 'expired');

    expect($package->fresh()->availability_status)->toBe(InvestmentPackageAvailabilityStatus::Expired->value);
});

it('expires due packages via the artisan command', function () {
    $duePackage = InvestmentPackage::factory()->dueToExpire()->create();
    $openPackage = InvestmentPackage::factory()->open()->create([
        'expires_at' => now()->addDay(),
    ]);

    Artisan::call('investment-packages:expire-due');

    expect($duePackage->fresh()->availability_status)->toBe(InvestmentPackageAvailabilityStatus::Expired->value);
    expect($openPackage->fresh()->availability_status)->toBe(InvestmentPackageAvailabilityStatus::Open->value);
});

it('returns not found for a missing investment package', function () {
    $token = adminBearerToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/999999')
        ->assertNotFound();
});

it('computes effective availability as full when joined count reaches capacity', function () {
    $token = adminBearerToken();
    $package = InvestmentPackage::factory()->create([
        'availability_status' => InvestmentPackageAvailabilityStatus::Open->value,
        'joined_count' => 10,
        'max_participants' => 10,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/investment-packages/'.$package->id)
        ->assertSuccessful()
        ->assertJsonPath('data.availability_status', 'open')
        ->assertJsonPath('data.effective_availability_status', 'full')
        ->assertJsonPath('data.remaining_seats', 0);
});
