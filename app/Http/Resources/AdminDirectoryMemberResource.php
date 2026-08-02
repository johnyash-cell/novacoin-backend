<?php

namespace App\Http\Resources;

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
            'email_verified_at' => $this->resource->email_verified_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
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
}
