<?php

namespace App\Services\Activity;

use App\Models\AdminNotificationRecipient;
use App\Models\User;

class MarksUserInAppNotificationAsRead
{
    public function markOne(User $user, AdminNotificationRecipient $recipient): AdminNotificationRecipient
    {
        // Ownership check — never mark another member's inbox row.
        abort_unless($recipient->user_id === $user->id, 404);

        if ($recipient->read_at === null) {
            $recipient->read_at = now();
            $recipient->save();
        }

        return $recipient->loadMissing('adminNotification');
    }

    public function markAll(User $user): int
    {
        return AdminNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
