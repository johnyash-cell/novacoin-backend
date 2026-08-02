<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPageVisitLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPageVisitLog>
 */
class UserPageVisitLogFactory extends Factory
{
    protected $model = UserPageVisitLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'page_path' => '/'.fake()->slug(2),
            'page_title' => fake()->sentence(3),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'referrer' => fake()->optional()->url(),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'traffic_source' => fake()->randomElement(['direct', 'app', 'referral', 'organic', 'email']),
            'visited_at' => now(),
        ];
    }
}
