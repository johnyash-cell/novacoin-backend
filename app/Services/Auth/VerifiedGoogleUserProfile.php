<?php

namespace App\Services\Auth;

readonly class VerifiedGoogleUserProfile
{
    public function __construct(
        public string $googleId,
        public string $email,
        public string $firstName,
        public string $lastName,
        public bool $isEmailVerified,
    ) {}
}
