<?php

namespace App\Exceptions;

use Exception;

class InvalidGoogleIdTokenException extends Exception
{
    public static function becauseTokenCouldNotBeVerified(): self
    {
        return new self('The Google ID token is invalid or has expired.');
    }
}
