<?php

namespace Database\Factories;

use App\Models\AuthenticationLoginLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthenticationLoginLog>
 */
class AuthenticationLoginLogFactory extends Factory
{
    protected $model = AuthenticationLoginLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => 'user',
            'actor_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'login_method' => 'password',
            'was_successful' => true,
            'failure_reason' => null,
        ];
    }

    public function failed(string $failureReason = 'Invalid email or password provided'): static
    {
        return $this->state(fn (array $attributes) => [
            'was_successful' => false,
            'failure_reason' => $failureReason,
            'actor_id' => null,
        ]);
    }

    public function forAdminActor(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_type' => 'admin',
        ]);
    }

    public function viaGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'login_method' => 'google',
        ]);
    }
}
