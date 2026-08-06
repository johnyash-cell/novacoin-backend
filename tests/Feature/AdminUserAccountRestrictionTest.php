<?php

use App\Enums\UserAccountStatus;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function restrictionAdminToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

function restrictionUserToken(User $user): string
{
    return auth('api')->login($user);
}

it('bans a user and blocks member login', function () {
    $user = User::factory()->create([
        'email' => 'banned-member@example.com',
        'password' => 'password',
    ]);

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/ban', [
            'reason' => 'Fraud suspected',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.account_status', UserAccountStatus::Banned->value)
        ->assertJsonPath('data.account_status_reason', 'Fraud suspected')
        ->assertJsonPath('data.suspended_until', null);

    $this->postJson('/api/auth/login', [
        'email' => 'banned-member@example.com',
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('status', false);
});

it('suspends a user until a future time and can unsuspend early', function () {
    $user = User::factory()->create();
    $suspendedUntil = now()->addDays(3)->toIso8601String();

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/suspend', [
            'suspended_until' => $suspendedUntil,
            'reason' => 'Chargeback review',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.account_status', UserAccountStatus::Suspended->value)
        ->assertJsonPath('data.account_status_reason', 'Chargeback review');

    expect($user->fresh()->suspended_until)->not->toBeNull();

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/unsuspend', [
            'reason' => 'Review cleared',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.account_status', UserAccountStatus::Active->value)
        ->assertJsonPath('data.suspended_until', null);
});

it('reactivates a banned user', function () {
    $user = User::factory()->banned()->create();

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/reactivate', [
            'reason' => 'Appeal accepted',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.account_status', UserAccountStatus::Active->value)
        ->assertJsonPath('data.account_status_reason', 'Appeal accepted');
});

it('rejects unsuspend when user is not suspended', function () {
    $user = User::factory()->banned()->create();

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/unsuspend')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only suspended user accounts can be unsuspended');
});

it('rejects reactivate when user is not banned', function () {
    $user = User::factory()->suspended()->create();

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/reactivate')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only banned user accounts can be reactivated');
});

it('blocks authenticated member api access while banned', function () {
    $user = User::factory()->create();
    $token = restrictionUserToken($user);

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/ban', [
            'reason' => 'Abuse',
        ])
        ->assertSuccessful();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/wallet')
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/auth/me')
        ->assertForbidden();
});

it('auto restores access after suspension end time passes', function () {
    $user = User::factory()->suspended(now()->subMinute(), 'Ended hold')->create();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.account_status', UserAccountStatus::Active->value);

    expect($user->fresh()->account_status)->toBe(UserAccountStatus::Active->value)
        ->and($user->fresh()->suspended_until)->toBeNull();
});

it('requires suspended_until when suspending', function () {
    $user = User::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.restrictionAdminToken())
        ->postJson('/api/admin/users/'.$user->id.'/suspend', [
            'reason' => 'Missing until',
        ])
        ->assertStatus(422);
});

it('rejects unauthenticated restriction mutations', function () {
    $user = User::factory()->create();

    $this->postJson('/api/admin/users/'.$user->id.'/ban')->assertUnauthorized();
    $this->postJson('/api/admin/users/'.$user->id.'/suspend', [
        'suspended_until' => now()->addDay()->toIso8601String(),
    ])->assertUnauthorized();
    $this->postJson('/api/admin/users/'.$user->id.'/unsuspend')->assertUnauthorized();
    $this->postJson('/api/admin/users/'.$user->id.'/reactivate')->assertUnauthorized();
});
