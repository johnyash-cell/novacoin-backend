<?php

namespace App\Http\Resources;

use App\Models\UserPageVisitLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserPageVisitLog
 */
class UserPageVisitLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'page_path' => $this->page_path,
            'page_title' => $this->page_title,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'referrer' => $this->referrer,
            'device_type' => $this->device_type,
            'traffic_source' => $this->traffic_source,
            'visited_at' => $this->visited_at,
            'created_at' => $this->created_at,
        ];
    }
}
