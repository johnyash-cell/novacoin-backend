<?php

namespace App\Services\Activity;

use App\Models\User;
use App\Models\UserPageVisitLog;
use Illuminate\Http\Request;

class RecordsUserPageVisit
{
    public function __construct(
        private ParsesVisitDeviceFromUserAgent $parsesVisitDeviceFromUserAgent,
        private CategorizesVisitTrafficSourceFromReferrer $categorizesVisitTrafficSourceFromReferrer,
    ) {}

    /**
     * @param  array{
     *     page_path: string,
     *     page_title?: string|null,
     *     referrer?: string|null,
     *     traffic_source?: string|null,
     * }  $validated
     */
    public function record(?User $user, array $validated, Request $request): UserPageVisitLog
    {
        $referrer = $validated['referrer'] ?? null;
        $applicationHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        $trafficSource = filled($validated['traffic_source'] ?? null)
            ? (string) $validated['traffic_source']
            : $this->categorizesVisitTrafficSourceFromReferrer->categorize($referrer, is_string($applicationHost) ? $applicationHost : null);

        return UserPageVisitLog::query()->create([
            'user_id' => $user?->id,
            'page_path' => $validated['page_path'],
            'page_title' => $validated['page_title'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $referrer,
            'device_type' => $this->parsesVisitDeviceFromUserAgent->parse($request->userAgent()),
            'traffic_source' => $trafficSource,
            'visited_at' => now(),
        ]);
    }
}
