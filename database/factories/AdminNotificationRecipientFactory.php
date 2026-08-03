<?php

namespace Database\Factories;

use App\Models\AdminNotification;
use App\Models\AdminNotificationRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminNotificationRecipient>
 */
class AdminNotificationRecipientFactory extends Factory
{
    protected $model = AdminNotificationRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_notification_id' => AdminNotification::factory(),
            'user_id' => User::factory(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'read_at' => now(),
        ]);
    }
}
