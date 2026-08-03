<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminNotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendsAdminInAppNotification
{
    /**
     * @param  array{
     *     title: string,
     *     message: string,
     *     audience_mode: string,
     *     user_ids?: list<int>|null,
     *     delivery: string,
     * }  $validated
     */
    public function send(Admin $admin, array $validated): AdminNotification
    {
        $recipientUserIds = $this->resolveRecipientUserIds($validated);

        if ($recipientUserIds === []) {
            throw ValidationException::withMessages([
                'audience_mode' => ['There are no users to notify. Add members before sending to all users.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $validated, $recipientUserIds): AdminNotification {
            $adminNotification = AdminNotification::query()->create([
                'admin_id' => $admin->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'audience_mode' => $validated['audience_mode'],
                'audience_count' => count($recipientUserIds),
                'delivery' => $validated['delivery'],
                'sent_at' => now(),
            ]);

            $now = now();

            // Persist one in-app row per member so a future inbox can read without resending.
            foreach (array_chunk($recipientUserIds, 500) as $recipientUserIdChunk) {
                $recipientRows = [];

                foreach ($recipientUserIdChunk as $userId) {
                    $recipientRows[] = [
                        'admin_notification_id' => $adminNotification->id,
                        'user_id' => $userId,
                        'read_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                AdminNotificationRecipient::query()->insert($recipientRows);
            }

            return $adminNotification->load('admin');
        });
    }

    /**
     * @param  array{
     *     audience_mode: string,
     *     user_ids?: list<int>|null,
     * }  $validated
     * @return list<int>
     */
    private function resolveRecipientUserIds(array $validated): array
    {
        if ($validated['audience_mode'] === 'selected_users') {
            return array_values(array_unique(array_map(
                static fn (mixed $userId): int => (int) $userId,
                $validated['user_ids'] ?? [],
            )));
        }

        return User::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->all();
    }
}
