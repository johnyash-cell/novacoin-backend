<?php

namespace App\Contracts\Auth;

use App\Exceptions\InvalidGoogleIdTokenException;
use App\Services\Auth\VerifiedGoogleUserProfile;

interface GoogleIdTokenVerifierContract
{
    /**
     * @throws InvalidGoogleIdTokenException
     */
    public function verify(string $idToken): VerifiedGoogleUserProfile;
}
