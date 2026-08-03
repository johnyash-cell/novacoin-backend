<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\AdminNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminNotification>
 */
class AdminNotificationFactory extends Factory
{
    protected $model = AdminNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(12),
            'audience_mode' => 'all_users',
            'audience_count' => fake()->numberBetween(1, 50),
            'delivery' => 'send_now',
            'sent_at' => now(),
        ];
    }
}
