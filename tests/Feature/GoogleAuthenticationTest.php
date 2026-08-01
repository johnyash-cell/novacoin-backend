<?php

use App\Contracts\Auth\GoogleIdTokenVerifierContract;
use App\Exceptions\InvalidGoogleIdTokenException;
use App\Models\User;
use App\Services\Auth\VerifiedGoogleUserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user from a verified google id token', function () {
    $this->mock(GoogleIdTokenVerifierContract::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('valid-google-id-token')
            ->andReturn(new VerifiedGoogleUserProfile(
                googleId: 'google-sub-123',
                email: 'new-google@example.com',
                firstName: 'Gena',
                lastName: 'User',
                isEmailVerified: true,
            ));
    });

    $response = $this->postJson('/api/auth/google', [
        'id_token' => 'valid-google-id-token',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user.email', 'new-google@example.com')
        ->assertJsonPath('data.user.first_name', 'Gena')
        ->assertJsonPath('data.user.has_google_linked', true)
        ->assertJsonStructure(['data' => ['token']]);

    $this->assertDatabaseHas('users', [
        'email' => 'new-google@example.com',
        'google_id' => 'google-sub-123',
        'password' => null,
    ]);
});

it('links google to an existing email account', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    $this->mock(GoogleIdTokenVerifierContract::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->andReturn(new VerifiedGoogleUserProfile(
                googleId: 'google-sub-456',
                email: 'existing@example.com',
                firstName: 'Existing',
                lastName: 'User',
                isEmailVerified: true,
            ));
    });

    $this->postJson('/api/auth/google', [
        'id_token' => 'valid-google-id-token',
    ])->assertSuccessful()
        ->assertJsonPath('data.user.email', 'existing@example.com')
        ->assertJsonPath('data.user.has_google_linked', true);

    expect($user->fresh()->google_id)->toBe('google-sub-456');
});

it('logs in an existing google-linked user by google id', function () {
    User::factory()->googleOnly('google-sub-789')->create([
        'email' => 'linked@example.com',
    ]);

    $this->mock(GoogleIdTokenVerifierContract::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->andReturn(new VerifiedGoogleUserProfile(
                googleId: 'google-sub-789',
                email: 'linked@example.com',
                firstName: 'Linked',
                lastName: 'User',
                isEmailVerified: true,
            ));
    });

    $this->postJson('/api/auth/google', [
        'id_token' => 'valid-google-id-token',
    ])->assertSuccessful()
        ->assertJsonPath('data.user.email', 'linked@example.com');

    expect(User::query()->where('email', 'linked@example.com')->count())->toBe(1);
});

it('rejects an invalid google id token', function () {
    $this->mock(GoogleIdTokenVerifierContract::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->andThrow(InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified());
    });

    $this->postJson('/api/auth/google', [
        'id_token' => 'invalid-token',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'The Google ID token is invalid or has expired.');
});
