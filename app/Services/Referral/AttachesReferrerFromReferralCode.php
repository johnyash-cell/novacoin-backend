<?php

namespace App\Services\Referral;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttachesReferrerFromReferralCode
{
    public function attach(User $newUser, string $referralCode): void
    {
        $normalizedCode = Str::upper(trim($referralCode));

        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'referral_code' => ['Enter a valid referral code.'],
            ]);
        }

        $referrer = User::query()
            ->where('referral_code', $normalizedCode)
            ->first();

        if ($referrer === null) {
            throw ValidationException::withMessages([
                'referral_code' => ['This referral code is invalid.'],
            ]);
        }

        // A member cannot refer themselves.
        if ((int) $referrer->id === (int) $newUser->id) {
            throw ValidationException::withMessages([
                'referral_code' => ['You cannot use your own referral code.'],
            ]);
        }

        // Referral link is set once at signup and never overwritten.
        if ($newUser->referred_by_user_id !== null) {
            return;
        }

        $newUser->forceFill([
            'referred_by_user_id' => $referrer->id,
        ])->save();
    }
}
