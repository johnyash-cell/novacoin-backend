<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'has_google_linked' => $this->hasGoogleLinked(),
            'has_admin_access' => $this->hasAdminBackofficeAccess(),
            'account_status' => $this->accountStatusValue(),
            'account_status_label' => $this->accountStatusLabel(),
            'account_status_reason' => $this->account_status_reason,
            'account_status_changed_at' => $this->account_status_changed_at,
            'account_status_changed_by_admin_id' => $this->account_status_changed_by_admin_id,
            'suspended_until' => $this->suspended_until,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
