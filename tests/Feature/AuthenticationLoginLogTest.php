<?php

use App\Models\Admin;
use App\Models\AuthenticationLoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records a successful user password login attempt', function () {
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->assertSuccessful();

    $this->assertDatabaseHas('authentication_login_logs', [
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'email' => $user->email,
        'login_method' => 'password',
        'was_successful' => true,
    ]);
});

it('records a failed user password login attempt', function () {
    User::factory()->create([
        'email' => 'member@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'member@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized();

    $this->assertDatabaseHas('authentication_login_logs', [
        'actor_type' => 'user',
        'email' => 'member@example.com',
        'login_method' => 'password',
        'was_successful' => false,
        'failure_reason' => 'Invalid email or password provided',
    ]);
});

it('records a successful admin password login attempt', function () {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/admin/auth/login', [
        'email' => $admin->email,
        'password' => 'Password1!',
    ])->assertSuccessful();

    $this->assertDatabaseHas('authentication_login_logs', [
        'actor_type' => 'admin',
        'actor_id' => $admin->id,
        'email' => $admin->email,
        'login_method' => 'password',
        'was_successful' => true,
    ]);
});

it('records a failed admin password login attempt', function () {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized();

    $this->assertDatabaseHas('authentication_login_logs', [
        'actor_type' => 'admin',
        'email' => 'admin@example.com',
        'login_method' => 'password',
        'was_successful' => false,
    ]);
});

it('lists authentication login logs for an authenticated admin', function () {
    AuthenticationLoginLog::factory()->count(2)->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/authentication-login-logs?page=1&per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonStructure(['data' => [['id', 'actor_type', 'email', 'was_successful']]]);
});

it('filters authentication login logs by actor type', function () {
    AuthenticationLoginLog::factory()->create(['actor_type' => 'user']);
    AuthenticationLoginLog::factory()->forAdminActor()->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/authentication-login-logs?actor_type=admin')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.actor_type', 'admin');
});

it('returns authentication login log filter options for an authenticated admin', function () {
    AuthenticationLoginLog::factory()->create(['actor_type' => 'user', 'login_method' => 'password']);
    AuthenticationLoginLog::factory()->forAdminActor()->viaGoogle()->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/authentication-login-logs/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 4);
});

it('rejects unauthenticated access to authentication login logs', function () {
    $this->getJson('/api/admin/authentication-login-logs')->assertUnauthorized();
});

it('lists authentication login logs for a particular user', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);
    $otherUser = User::factory()->create(['email' => 'other@example.com']);

    AuthenticationLoginLog::factory()->create([
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'email' => $user->email,
        'was_successful' => true,
    ]);
    AuthenticationLoginLog::factory()->failed()->create([
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'email' => $user->email,
    ]);
    AuthenticationLoginLog::factory()->create([
        'actor_type' => 'user',
        'actor_id' => $otherUser->id,
        'email' => $otherUser->email,
    ]);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/users/'.$user->id.'/authentication-login-logs')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.filters.user_id', $user->id)
        ->assertJsonPath('data.0.email', $user->email);
});

it('filters global authentication login logs by user id query param', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);
    $otherUser = User::factory()->create(['email' => 'other@example.com']);

    AuthenticationLoginLog::factory()->create([
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'email' => $user->email,
    ]);
    AuthenticationLoginLog::factory()->create([
        'actor_type' => 'user',
        'actor_id' => $otherUser->id,
        'email' => $otherUser->email,
    ]);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/authentication-login-logs?user_id='.$user->id)
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.filters.user_id', $user->id);
});

it('includes admin backoffice login logs on a promoted user profile', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);
    $admin = Admin::factory()->create(['email' => $user->email]);

    AuthenticationLoginLog::factory()->create([
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'email' => $user->email,
        'was_successful' => true,
    ]);
    AuthenticationLoginLog::factory()->forAdminActor()->create([
        'actor_id' => $admin->id,
        'email' => $user->email,
        'was_successful' => true,
    ]);
    AuthenticationLoginLog::factory()->forAdminActor()->create([
        'email' => 'other-admin@example.com',
    ]);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/users/'.$user->id.'/authentication-login-logs')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.filters.user_id', $user->id);
});
