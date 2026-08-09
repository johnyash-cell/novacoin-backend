<?php

namespace App\Http\Resources;

use App\Enums\UserAccountRestrictionLogAction;
use App\Enums\UserAccountStatus;
use App\Models\Admin;
use App\Models\UserAccountRestrictionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserAccountRestrictionLog
 */
class AdminUserAccountRestrictionLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $action = UserAccountRestrictionLogAction::tryFrom((string) $this->action);
        $previousStatus = UserAccountStatus::tryFrom((string) $this->previous_account_status);
        $newStatus = UserAccountStatus::tryFrom((string) $this->new_account_status);
        $performedByAdmin = $this->resolved_performed_by_admin instanceof Admin
            ? $this->resolved_performed_by_admin
            : $this->resolvePerformedByAdmin();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => (string) $this->action,
            'action_label' => $action?->label()
                ?? ucfirst(str_replace('_', ' ', (string) $this->action)),
            'previous_account_status' => (string) $this->previous_account_status,
            'previous_account_status_label' => $previousStatus?->label()
                ?? ucfirst(str_replace('_', ' ', (string) $this->previous_account_status)),
            'new_account_status' => (string) $this->new_account_status,
            'new_account_status_label' => $newStatus?->label()
                ?? ucfirst(str_replace('_', ' ', (string) $this->new_account_status)),
            'reason' => $this->reason,
            'suspended_until' => $this->suspended_until,
            'performed_by_admin_id' => $this->performed_by_admin_id,
            'performed_by_admin' => $performedByAdmin === null ? null : [
                'id' => $performedByAdmin->id,
                'first_name' => $performedByAdmin->first_name,
                'last_name' => $performedByAdmin->last_name,
                'email' => $performedByAdmin->email,
            ],
            'created_at' => $this->created_at,
        ];
    }

    private function resolvePerformedByAdmin(): ?Admin
    {
        if ($this->performed_by_admin_id === null) {
            return null;
        }

        return Admin::query()->find($this->performed_by_admin_id);
    }
}
