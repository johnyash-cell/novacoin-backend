<?php

namespace App\Services\Activity;

class CategorizesVisitTrafficSourceFromReferrer
{
    /**
     * @var list<string>
     */
    private const ORGANIC_SEARCH_HOST_FRAGMENTS = [
        'google.',
        'bing.',
        'yahoo.',
        'duckduckgo.',
        'baidu.',
    ];

    /**
     * @var list<string>
     */
    private const EMAIL_REFERRER_FRAGMENTS = [
        'mail.',
        'outlook.',
        'gmail.',
        'proton.',
    ];

    public function categorize(?string $referrer, ?string $applicationHost = null): string
    {
        if (blank($referrer)) {
            return 'direct';
        }

        $referrerHost = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($referrerHost) || $referrerHost === '') {
            return 'direct';
        }

        $normalizedReferrerHost = strtolower($referrerHost);
        $normalizedApplicationHost = strtolower((string) ($applicationHost ?? ''));

        if ($normalizedApplicationHost !== '' && $normalizedReferrerHost === $normalizedApplicationHost) {
            return 'direct';
        }

        foreach (self::ORGANIC_SEARCH_HOST_FRAGMENTS as $searchHostFragment) {
            if (str_contains($normalizedReferrerHost, $searchHostFragment)) {
                return 'organic';
            }
        }

        foreach (self::EMAIL_REFERRER_FRAGMENTS as $emailReferrerFragment) {
            if (str_contains($normalizedReferrerHost, $emailReferrerFragment)) {
                return 'email';
            }
        }

        if (str_contains(strtolower($referrer), 'android-app://')
            || str_contains(strtolower($referrer), 'ios-app://')) {
            return 'app';
        }

        return 'referral';
    }

    public function resolveLabel(string $trafficSource): string
    {
        return match ($trafficSource) {
            'direct' => 'Direct',
            'app' => 'App',
            'referral' => 'Referral',
            'organic' => 'Organic',
            'email' => 'Email',
            default => ucfirst(str_replace('_', ' ', $trafficSource)),
        };
    }
}
