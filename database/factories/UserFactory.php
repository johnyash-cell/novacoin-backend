<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => null,
            'google_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Google-only account with no password.
     */
    public function googleOnly(?string $googleId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'google_id' => $googleId ?? (string) fake()->unique()->numerify('#####################'),
            'email_verified_at' => now(),
        ]);
    }

    public function banned(?string $reason = 'Policy violation'): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'banned',
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'suspended_until' => null,
        ]);
    }

    public function suspended(?\DateTimeInterface $until = null, ?string $reason = 'Temporary hold'): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'suspended',
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'suspended_until' => $until ?? now()->addDays(7),
        ]);
    }
}
