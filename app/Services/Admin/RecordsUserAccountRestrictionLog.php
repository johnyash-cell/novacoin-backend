<?php

namespace App\Services\Admin;

use App\Enums\UserAccountRestrictionLogAction;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserAccountRestrictionLog;
use Carbon\CarbonInterface;

class RecordsUserAccountRestrictionLog
{
    public function record(
        User $user,
        UserAccountRestrictionLogAction $action,
        string $previousAccountStatus,
        string $newAccountStatus,
        ?Admin $performedByAdmin = null,
        ?string $reason = null,
        ?CarbonInterface $suspendedUntil = null,
    ): UserAccountRestrictionLog {
        return UserAccountRestrictionLog::query()->create([
            'user_id' => $user->id,
            'action' => $action->value,
            'previous_account_status' => $previousAccountStatus,
            'new_account_status' => $newAccountStatus,
            'reason' => $reason,
            'suspended_until' => $suspendedUntil,
            'performed_by_admin_id' => $performedByAdmin?->id,
            'created_at' => now(),
        ]);
    }
}
