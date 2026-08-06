<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GoogleIdTokenVerifierContract;
use App\Models\User;
use App\Services\Referral\AttachesReferrerFromReferralCode;
use Illuminate\Support\Facades\DB;

class GoogleUserAuthenticationService
{
    public function __construct(
        private GoogleIdTokenVerifierContract $googleIdTokenVerifier,
        private AttachesReferrerFromReferralCode $attachesReferrerFromReferralCode,
    ) {}

    public function authenticateWithIdToken(string $idToken, ?string $referralCode = null): User
    {
        $profile = $this->googleIdTokenVerifier->verify($idToken);

        $user = User::query()->where('google_id', $profile->googleId)->first();

        if ($user !== null) {
            return $user;
        }

        $user = User::query()->where('email', $profile->email)->first();

        if ($user !== null) {
            $user->google_id = $profile->googleId;

            if ($profile->isEmailVerified && $user->email_verified_at === null) {
                $user->email_verified_at = now();
            }

            $user->save();

            return $user;
        }

        // Create + referral attach must commit together for new Google accounts.
        return DB::transaction(function () use ($profile, $referralCode): User {
            $user = User::query()->create([
                'first_name' => $profile->firstName,
                'last_name' => $profile->lastName,
                'email' => $profile->email,
                'google_id' => $profile->googleId,
                'password' => null,
                'email_verified_at' => $profile->isEmailVerified ? now() : null,
            ]);

            if (filled($referralCode)) {
                $this->attachesReferrerFromReferralCode->attach($user, $referralCode);
            }

            return $user->fresh() ?? $user;
        });
    }
}
