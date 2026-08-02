<?php

namespace App\Services\Activity;

class ParsesVisitDeviceFromUserAgent
{
    public function parse(?string $userAgent): string
    {
        if (blank($userAgent)) {
            return 'desktop';
        }

        $normalizedUserAgent = strtolower($userAgent);

        if (str_contains($normalizedUserAgent, 'ipad')
            || (str_contains($normalizedUserAgent, 'tablet') && ! str_contains($normalizedUserAgent, 'mobile'))) {
            return 'tablet';
        }

        if (str_contains($normalizedUserAgent, 'mobile')
            || str_contains($normalizedUserAgent, 'iphone')
            || str_contains($normalizedUserAgent, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }
}
