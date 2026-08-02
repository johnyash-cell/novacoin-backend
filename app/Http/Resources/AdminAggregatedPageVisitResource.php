<?php

namespace App\Http\Resources;

use App\Services\Activity\CategorizesVisitTrafficSourceFromReferrer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AdminAggregatedPageVisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trafficSource = (string) ($this->resource->traffic_source ?? 'direct');
        $deviceType = (string) ($this->resource->device_type ?? 'desktop');
        $pagePath = (string) $this->resource->page_path;
        $userId = $this->resource->user_id;
        $visitorEmail = $this->resource->visitor_email ?? null;

        $pageLabel = filled($this->resource->page_title ?? null)
            ? (string) $this->resource->page_title
            : $this->humanizePagePath($pagePath);

        $visitorDisplayName = null;
        $visitorUsername = null;

        if ($userId !== null && filled($this->resource->visitor_first_name ?? null)) {
            $visitorDisplayName = trim(
                ($this->resource->visitor_first_name ?? '').' '.($this->resource->visitor_last_name ?? ''),
            );
            $visitorUsername = $this->resolveVisitorUsername($visitorEmail);
        }

        return [
            'id' => $this->buildAggregatedRowId($pagePath, $userId),
            'path' => $pagePath,
            'page_label' => $pageLabel,
            'visitor_display_name' => $visitorDisplayName,
            'visitor_username' => $visitorUsername,
            'member_id' => $userId !== null ? (int) $userId : null,
            'visit_count' => (int) $this->resource->visit_count,
            'last_seen_at' => $this->resource->last_seen_at,
            'device' => $deviceType,
            'source_label' => app(CategorizesVisitTrafficSourceFromReferrer::class)->resolveLabel($trafficSource),
        ];
    }

    private function buildAggregatedRowId(string $pagePath, mixed $userId): string
    {
        $visitorSegment = $userId !== null ? (string) $userId : 'anonymous';

        return 'page-visit-'.md5($pagePath.'::'.$visitorSegment);
    }

    private function humanizePagePath(string $pagePath): string
    {
        $finalSegment = Str::afterLast(rtrim($pagePath, '/'), '/');

        if ($finalSegment === '') {
            return 'Home';
        }

        return Str::headline(str_replace('-', ' ', $finalSegment));
    }

    private function resolveVisitorUsername(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        $username = Str::before($email, '@');

        return $username !== '' ? $username : null;
    }
}
