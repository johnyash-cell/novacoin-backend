<?php

namespace App\Services\Auth;

use App\Models\Admin;
use App\Models\AuthenticationLoginLog;
use App\Models\User;
use Illuminate\Http\Request;

class RecordsAuthenticationLoginAttempt
{
    public function recordSuccessfulUserPasswordLogin(User $user, Request $request): AuthenticationLoginLog
    {
        return $this->recordAttempt(
            actorType: 'user',
            actorId: $user->id,
            email: $user->email,
            loginMethod: 'password',
            wasSuccessful: true,
            request: $request,
        );
    }

    public function recordFailedUserPasswordLogin(string $email, Request $request, string $failureReason): AuthenticationLoginLog
    {
        $user = User::query()->where('email', $email)->first();

        return $this->recordAttempt(
            actorType: 'user',
            actorId: $user?->id,
            email: $email,
            loginMethod: 'password',
            wasSuccessful: false,
            request: $request,
            failureReason: $failureReason,
        );
    }

    public function recordSuccessfulUserGoogleLogin(User $user, Request $request): AuthenticationLoginLog
    {
        return $this->recordAttempt(
            actorType: 'user',
            actorId: $user->id,
            email: $user->email,
            loginMethod: 'google',
            wasSuccessful: true,
            request: $request,
        );
    }

    public function recordFailedUserGoogleLogin(string $email, Request $request, string $failureReason): AuthenticationLoginLog
    {
        return $this->recordAttempt(
            actorType: 'user',
            actorId: null,
            email: $email,
            loginMethod: 'google',
            wasSuccessful: false,
            request: $request,
            failureReason: $failureReason,
        );
    }

    public function recordSuccessfulAdminPasswordLogin(Admin $admin, Request $request): AuthenticationLoginLog
    {
        return $this->recordAttempt(
            actorType: 'admin',
            actorId: $admin->id,
            email: $admin->email,
            loginMethod: 'password',
            wasSuccessful: true,
            request: $request,
        );
    }

    public function recordFailedAdminPasswordLogin(string $email, Request $request, string $failureReason): AuthenticationLoginLog
    {
        $admin = Admin::query()->where('email', $email)->first();

        return $this->recordAttempt(
            actorType: 'admin',
            actorId: $admin?->id,
            email: $email,
            loginMethod: 'password',
            wasSuccessful: false,
            request: $request,
            failureReason: $failureReason,
        );
    }

    private function recordAttempt(
        string $actorType,
        ?int $actorId,
        string $email,
        string $loginMethod,
        bool $wasSuccessful,
        Request $request,
        ?string $failureReason = null,
    ): AuthenticationLoginLog {
        return AuthenticationLoginLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_method' => $loginMethod,
            'was_successful' => $wasSuccessful,
            'failure_reason' => $failureReason,
        ]);
    }
}
