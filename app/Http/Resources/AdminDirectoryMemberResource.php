<?php

namespace App\Http\Resources;

use App\Enums\UserAccountStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDirectoryMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recordType = (string) ($this->resource->record_type ?? '');
        $role = (string) ($this->resource->role ?? 'user');

        return [
            'record_type' => $recordType,
            'user_id' => $this->resource->user_id,
            'admin_id' => $this->resource->admin_id,
            'id' => $this->resource->user_id ?? $this->resource->admin_id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'role' => $role,
            'role_label' => $this->resolveRoleLabel($role),
            'has_google_linked' => filled($this->resource->google_id ?? null),
            'has_admin_access' => in_array($role, ['admin', 'super_admin'], true),
            'is_super_admin' => $role === 'super_admin',
            'account_status' => (string) ($this->resource->account_status ?? 'active'),
            'account_status_label' => $this->resolveAccountStatusLabel(
                (string) ($this->resource->account_status ?? 'active'),
            ),
            'suspended_until' => $this->resource->suspended_until ?? null,
            'wallet' => $this->resolveWalletPayload(),
            'email_verified_at' => $this->resource->email_verified_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    /**
     * @return array{available_balance: float, currency_code: string}|null
     */
    private function resolveWalletPayload(): ?array
    {
        if (($this->resource->record_type ?? null) !== 'user') {
            return null;
        }

        $availableBalance = $this->resource->wallet_available_balance ?? null;

        return [
            'available_balance' => $availableBalance === null ? 0.0 : (float) $availableBalance,
            'currency_code' => (string) ($this->resource->wallet_currency_code ?? 'USD'),
        ];
    }

    private function resolveRoleLabel(string $role): string
    {
        return match ($role) {
            'user' => 'User',
            'admin' => 'Admin',
            'super_admin' => 'Super Admin',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    private function resolveAccountStatusLabel(string $accountStatus): string
    {
        return UserAccountStatus::tryFrom($accountStatus)?->label()
            ?? ucfirst(str_replace('_', ' ', $accountStatus));
    }
}
