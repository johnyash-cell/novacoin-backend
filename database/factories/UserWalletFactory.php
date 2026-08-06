<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWallet>
 */
class UserWalletFactory extends Factory
{
    protected $model = UserWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'available_balance' => 0,
            'currency_code' => 'USD',
        ];
    }
}
