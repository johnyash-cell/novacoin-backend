<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a user and returns a jwt', function () {
    $response = $this->postJson('/api/auth/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'phone' => '+15551234567',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.user.first_name', 'Jane')
        ->assertJsonPath('data.user.has_google_linked', false)
        ->assertJsonStructure([
            'data' => ['token', 'token_type', 'expires_in', 'user'],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
});

it('logs in a user with email and password', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonStructure(['data' => ['token']]);
});

it('rejects invalid user credentials', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Invalid email or password provided');
});

it('returns the authenticated user profile', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.first_name', $user->first_name);
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout')
        ->assertSuccessful()
        ->assertJsonPath('message', 'User logged out successfully');
});

it('rejects a user token on admin routes', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/auth/me')
        ->assertUnauthorized();
});

it('rejects email login when the user has no password and must use google', function () {
    User::factory()->googleOnly()->create([
        'email' => 'google-user@example.com',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'google-user@example.com',
        'password' => 'Password1!',
    ])->assertUnauthorized()
        ->assertJsonPath(
            'message',
            'This account uses Google sign-in. Please continue with Google.',
        );
});

it('rejects unauthenticated access to user me', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('status', false);
});
