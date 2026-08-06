<?php

namespace App\Services\Auth;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Carbon\Carbon;

class ResolvesUserAccountAccessRestrictionMessage
{
    /**
     * Clears an expired timed suspension, then returns a member-facing block message when access is denied.
     */
    public function restrictionMessageOrNull(User $user): ?string
    {
        // Always re-read restriction columns — in-memory auth user can be stale after an admin ban/suspend.
        $user->refresh();

        $this->clearExpiredSuspensionIfNeeded($user);

        $status = UserAccountStatus::tryFrom((string) ($user->account_status ?? UserAccountStatus::Active->value));

        if ($status === UserAccountStatus::Banned) {
            $reason = filled($user->account_status_reason)
                ? ' Reason: '.$user->account_status_reason
                : '';

            return 'Your account has been banned. Contact support for help.'.$reason;
        }

        if ($status === UserAccountStatus::Suspended) {
            $until = $user->suspended_until instanceof Carbon
                ? $user->suspended_until->toIso8601String()
                : null;

            $untilSuffix = $until !== null ? ' until '.$until : '';
            $reason = filled($user->account_status_reason)
                ? ' Reason: '.$user->account_status_reason
                : '';

            return 'Your account is suspended'.$untilSuffix.'.'.$reason;
        }

        return null;
    }

    public function clearExpiredSuspensionIfNeeded(User $user): void
    {
        $status = (string) ($user->account_status ?? UserAccountStatus::Active->value);

        if ($status !== UserAccountStatus::Suspended->value) {
            return;
        }

        if ($user->suspended_until === null || $user->suspended_until->isFuture()) {
            return;
        }

        // Timed suspension ended — restore access without waiting for admin unsuspend.
        $user->forceFill([
            'account_status' => UserAccountStatus::Active->value,
            'account_status_reason' => null,
            'account_status_changed_at' => now(),
            'account_status_changed_by_admin_id' => null,
            'suspended_until' => null,
        ])->save();
    }
}
