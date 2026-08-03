<?php

namespace App\Http\Resources;

use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdminNotification
 */
class AdminNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $audienceMode = $this->audience_mode instanceof \BackedEnum
            ? $this->audience_mode->value
            : (string) ($this->audience_mode ?? '');

        $delivery = $this->delivery instanceof \BackedEnum
            ? $this->delivery->value
            : (string) ($this->delivery ?? '');

        $sentBy = null;

        if ($this->relationLoaded('admin') && $this->admin !== null) {
            $sentBy = [
                'admin_id' => $this->admin->id,
                'name' => trim(($this->admin->first_name ?? '').' '.($this->admin->last_name ?? '')),
                'email' => $this->admin->email,
            ];
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'audience_mode' => $audienceMode,
            'audience_label' => $this->resolveAudienceLabel($audienceMode),
            'audience_count' => $this->audience_count,
            'delivery' => $delivery,
            'sent_at' => $this->sent_at,
            'sent_by' => $sentBy,
            'created_at' => $this->created_at,
        ];
    }

    private function resolveAudienceLabel(string $audienceMode): string
    {
        return match ($audienceMode) {
            'all_users' => 'All users',
            'selected_users' => 'Selected users',
            default => ucfirst(str_replace('_', ' ', $audienceMode)),
        };
    }
}
