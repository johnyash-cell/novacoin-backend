<?php

namespace App\Services\Admin;

use App\Enums\UserAccountStatus;
use App\Models\Admin;
use App\Models\User;
use Carbon\CarbonInterface;
use RuntimeException;

class ManagesUserAccountRestrictionStatus
{
    public function ban(User $user, Admin $admin, ?string $reason = null): User
    {
        if ((string) $user->account_status === UserAccountStatus::Banned->value) {
            throw new RuntimeException('This user account is already banned');
        }

        $user->forceFill([
            'account_status' => UserAccountStatus::Banned->value,
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'account_status_changed_by_admin_id' => $admin->id,
            'suspended_until' => null,
        ])->save();

        return $user->fresh();
    }

    public function suspend(
        User $user,
        Admin $admin,
        CarbonInterface $suspendedUntil,
        ?string $reason = null,
    ): User {
        if ($suspendedUntil->lessThanOrEqualTo(now())) {
            throw new RuntimeException('Suspension end time must be in the future');
        }

        $user->forceFill([
            'account_status' => UserAccountStatus::Suspended->value,
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'account_status_changed_by_admin_id' => $admin->id,
            'suspended_until' => $suspendedUntil,
        ])->save();

        return $user->fresh();
    }

    public function unsuspend(User $user, Admin $admin, ?string $reason = null): User
    {
        if ((string) $user->account_status !== UserAccountStatus::Suspended->value) {
            throw new RuntimeException('Only suspended user accounts can be unsuspended');
        }

        $user->forceFill([
            'account_status' => UserAccountStatus::Active->value,
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'account_status_changed_by_admin_id' => $admin->id,
            'suspended_until' => null,
        ])->save();

        return $user->fresh();
    }

    public function reactivate(User $user, Admin $admin, ?string $reason = null): User
    {
        if ((string) $user->account_status !== UserAccountStatus::Banned->value) {
            throw new RuntimeException('Only banned user accounts can be reactivated');
        }

        $user->forceFill([
            'account_status' => UserAccountStatus::Active->value,
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'account_status_changed_by_admin_id' => $admin->id,
            'suspended_until' => null,
        ])->save();

        return $user->fresh();
    }
}
