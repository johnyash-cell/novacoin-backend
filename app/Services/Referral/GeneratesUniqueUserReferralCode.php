<?php

namespace App\Services\Referral;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratesUniqueUserReferralCode
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = Str::upper(Str::random(8));

            if (! User::query()->where('referral_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique referral code.');
    }
}
