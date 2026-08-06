<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\UserWallet;

class ResolvesUserWallet
{
    public function resolveForUser(User $user): UserWallet
    {
        return UserWallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'currency_code' => 'USD',
            ],
        );
    }
}
