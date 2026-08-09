<?php

namespace Database\Factories;

use App\Enums\UserAccountRestrictionLogAction;
use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Models\UserAccountRestrictionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAccountRestrictionLog>
 */
class UserAccountRestrictionLogFactory extends Factory
{
    protected $model = UserAccountRestrictionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => UserAccountRestrictionLogAction::Ban->value,
            'previous_account_status' => UserAccountStatus::Active->value,
            'new_account_status' => UserAccountStatus::Banned->value,
            'reason' => fake()->optional()->sentence(),
            'suspended_until' => null,
            'performed_by_admin_id' => null,
            'created_at' => now(),
        ];
    }
}
