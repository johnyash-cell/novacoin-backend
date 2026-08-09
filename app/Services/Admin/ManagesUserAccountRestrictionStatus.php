<?php

namespace App\Services\Admin;

use App\Enums\UserAccountRestrictionLogAction;
use App\Enums\UserAccountStatus;
use App\Models\Admin;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ManagesUserAccountRestrictionStatus
{
    public function __construct(
        private RecordsUserAccountRestrictionLog $recordsUserAccountRestrictionLog,
    ) {}

    public function ban(User $user, Admin $admin, ?string $reason = null): User
    {
        if ((string) $user->account_status === UserAccountStatus::Banned->value) {
            throw new RuntimeException('This user account is already banned');
        }

        return DB::transaction(function () use ($user, $admin, $reason): User {
            $previousStatus = (string) ($user->account_status ?? UserAccountStatus::Active->value);

            $user->forceFill([
                'account_status' => UserAccountStatus::Banned->value,
                'account_status_reason' => $reason,
                'account_status_changed_at' => now(),
                'account_status_changed_by_admin_id' => $admin->id,
                'suspended_until' => null,
            ])->save();

            $this->recordsUserAccountRestrictionLog->record(
                user: $user,
                action: UserAccountRestrictionLogAction::Ban,
                previousAccountStatus: $previousStatus,
                newAccountStatus: UserAccountStatus::Banned->value,
                performedByAdmin: $admin,
                reason: $reason,
            );

            return $user->fresh() ?? $user;
        });
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

        return DB::transaction(function () use ($user, $admin, $suspendedUntil, $reason): User {
            $previousStatus = (string) ($user->account_status ?? UserAccountStatus::Active->value);

            $user->forceFill([
                'account_status' => UserAccountStatus::Suspended->value,
                'account_status_reason' => $reason,
                'account_status_changed_at' => now(),
                'account_status_changed_by_admin_id' => $admin->id,
                'suspended_until' => $suspendedUntil,
            ])->save();

            $this->recordsUserAccountRestrictionLog->record(
                user: $user,
                action: UserAccountRestrictionLogAction::Suspend,
                previousAccountStatus: $previousStatus,
                newAccountStatus: UserAccountStatus::Suspended->value,
                performedByAdmin: $admin,
                reason: $reason,
                suspendedUntil: $suspendedUntil,
            );

            return $user->fresh() ?? $user;
        });
    }

    public function unsuspend(User $user, Admin $admin, ?string $reason = null): User
    {
        if ((string) $user->account_status !== UserAccountStatus::Suspended->value) {
            throw new RuntimeException('Only suspended user accounts can be unsuspended');
        }

        return DB::transaction(function () use ($user, $admin, $reason): User {
            $previousStatus = (string) $user->account_status;

            $user->forceFill([
                'account_status' => UserAccountStatus::Active->value,
                'account_status_reason' => $reason,
                'account_status_changed_at' => now(),
                'account_status_changed_by_admin_id' => $admin->id,
                'suspended_until' => null,
            ])->save();

            $this->recordsUserAccountRestrictionLog->record(
                user: $user,
                action: UserAccountRestrictionLogAction::Unsuspend,
                previousAccountStatus: $previousStatus,
                newAccountStatus: UserAccountStatus::Active->value,
                performedByAdmin: $admin,
                reason: $reason,
            );

            return $user->fresh() ?? $user;
        });
    }

    public function reactivate(User $user, Admin $admin, ?string $reason = null): User
    {
        if ((string) $user->account_status !== UserAccountStatus::Banned->value) {
            throw new RuntimeException('Only banned user accounts can be reactivated');
        }

        return DB::transaction(function () use ($user, $admin, $reason): User {
            $previousStatus = (string) $user->account_status;

            $user->forceFill([
                'account_status' => UserAccountStatus::Active->value,
                'account_status_reason' => $reason,
                'account_status_changed_at' => now(),
                'account_status_changed_by_admin_id' => $admin->id,
                'suspended_until' => null,
            ])->save();

            $this->recordsUserAccountRestrictionLog->record(
                user: $user,
                action: UserAccountRestrictionLogAction::Reactivate,
                previousAccountStatus: $previousStatus,
                newAccountStatus: UserAccountStatus::Active->value,
                performedByAdmin: $admin,
                reason: $reason,
            );

            return $user->fresh() ?? $user;
        });
    }
}
