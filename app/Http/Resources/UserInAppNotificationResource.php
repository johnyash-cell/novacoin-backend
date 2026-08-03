<?php

namespace App\Http\Resources;

use App\Models\AdminNotificationRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdminNotificationRecipient
 */
class UserInAppNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $broadcast = $this->adminNotification;

        return [
            'id' => $this->id,
            'title' => $broadcast?->title,
            'body' => $broadcast?->message,
            'message' => $broadcast?->message,
            'sent_at' => $broadcast?->sent_at,
            'is_unread' => $this->read_at === null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
