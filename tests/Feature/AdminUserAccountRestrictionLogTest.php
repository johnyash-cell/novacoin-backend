<?php

use App\Enums\UserAccountRestrictionLogAction;
use App\Enums\UserAccountStatus;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserAccountRestrictionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function restrictionLogAdminToken(?Admin $admin = null): string
{
    return auth('admin')->login($admin ?? Admin::factory()->create());
}

it('writes a restriction log when an admin bans a user', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.restrictionLogAdminToken($admin))
        ->postJson('/api/admin/users/'.$user->id.'/ban', [
            'reason' => 'Fraud suspected',
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('user_account_restriction_logs', [
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Ban->value,
        'previous_account_status' => UserAccountStatus::Active->value,
        'new_account_status' => UserAccountStatus::Banned->value,
        'reason' => 'Fraud suspected',
        'performed_by_admin_id' => $admin->id,
    ]);
});

it('writes logs for suspend and unsuspend', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->create();
    $token = restrictionLogAdminToken($admin);
    $suspendedUntil = now()->addDays(2)->toIso8601String();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/users/'.$user->id.'/suspend', [
            'suspended_until' => $suspendedUntil,
            'reason' => 'Chargeback',
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('user_account_restriction_logs', [
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Suspend->value,
        'new_account_status' => UserAccountStatus::Suspended->value,
        'reason' => 'Chargeback',
        'performed_by_admin_id' => $admin->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/users/'.$user->id.'/unsuspend', [
            'reason' => 'Cleared',
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('user_account_restriction_logs', [
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Unsuspend->value,
        'new_account_status' => UserAccountStatus::Active->value,
        'reason' => 'Cleared',
    ]);

    expect(UserAccountRestrictionLog::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('writes a reactivate log for a banned user', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->banned()->create();

    $this->withHeader('Authorization', 'Bearer '.restrictionLogAdminToken($admin))
        ->postJson('/api/admin/users/'.$user->id.'/reactivate', [
            'reason' => 'Appeal accepted',
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('user_account_restriction_logs', [
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Reactivate->value,
        'previous_account_status' => UserAccountStatus::Banned->value,
        'new_account_status' => UserAccountStatus::Active->value,
        'performed_by_admin_id' => $admin->id,
    ]);
});

it('writes a suspension_expired log when timed suspension ends on login', function () {
    $user = User::factory()->suspended(now()->subMinute(), 'Ended hold')->create([
        'password' => 'password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSuccessful();

    $this->assertDatabaseHas('user_account_restriction_logs', [
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::SuspensionExpired->value,
        'previous_account_status' => UserAccountStatus::Suspended->value,
        'new_account_status' => UserAccountStatus::Active->value,
        'performed_by_admin_id' => null,
    ]);
});

it('lists restriction logs for a user with pagination', function () {
    $admin = Admin::factory()->create([
        'first_name' => 'Ops',
        'last_name' => 'Lead',
        'email' => 'ops@example.com',
    ]);
    $user = User::factory()->create();

    UserAccountRestrictionLog::factory()->create([
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Ban->value,
        'performed_by_admin_id' => $admin->id,
        'created_at' => now()->subDay(),
    ]);
    UserAccountRestrictionLog::factory()->create([
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Reactivate->value,
        'previous_account_status' => UserAccountStatus::Banned->value,
        'new_account_status' => UserAccountStatus::Active->value,
        'performed_by_admin_id' => $admin->id,
        'created_at' => now(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.restrictionLogAdminToken($admin))
        ->getJson('/api/admin/users/'.$user->id.'/account-restriction-logs?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('message', 'User account restriction logs fetched successfully')
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.action', UserAccountRestrictionLogAction::Reactivate->value)
        ->assertJsonPath('data.0.performed_by_admin.email', 'ops@example.com');
});

it('returns filter options without a search filter', function () {
    $user = User::factory()->create();
    UserAccountRestrictionLog::factory()->create([
        'user_id' => $user->id,
        'action' => UserAccountRestrictionLogAction::Suspend->value,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.restrictionLogAdminToken())
        ->getJson('/api/admin/users/'.$user->id.'/account-restriction-logs/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 2);

    $filterKeys = collect($response->json('data.filters'))->pluck('key')->all();

    expect($filterKeys)->toBe(['action', 'date_range'])
        ->and($filterKeys)->not->toContain('search');
});

it('rejects unauthenticated restriction log access', function () {
    $user = User::factory()->create();

    $this->getJson('/api/admin/users/'.$user->id.'/account-restriction-logs')->assertUnauthorized();
    $this->getJson('/api/admin/users/'.$user->id.'/account-restriction-logs/filter-options')->assertUnauthorized();
});
