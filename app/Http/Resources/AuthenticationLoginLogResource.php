<?php

namespace App\Http\Resources;

use App\Models\AuthenticationLoginLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthenticationLoginLog
 */
class AuthenticationLoginLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actorType = $this->actor_type instanceof \BackedEnum
            ? $this->actor_type->value
            : (string) ($this->actor_type ?? '');

        $loginMethod = $this->login_method instanceof \BackedEnum
            ? $this->login_method->value
            : (string) ($this->login_method ?? '');

        return [
            'id' => $this->id,
            'actor_type' => $actorType,
            'actor_type_label' => $this->resolveActorTypeLabel($actorType),
            'actor_id' => $this->actor_id,
            'email' => $this->email,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'login_method' => $loginMethod,
            'login_method_label' => $this->resolveLoginMethodLabel($loginMethod),
            'was_successful' => $this->was_successful,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];
    }

    private function resolveActorTypeLabel(string $actorType): string
    {
        return match ($actorType) {
            'user' => 'User',
            'admin' => 'Admin',
            default => ucfirst(str_replace('_', ' ', $actorType)),
        };
    }

    private function resolveLoginMethodLabel(string $loginMethod): string
    {
        return match ($loginMethod) {
            'password' => 'Password',
            'google' => 'Google',
            default => ucfirst(str_replace('_', ' ', $loginMethod)),
        };
    }
}
