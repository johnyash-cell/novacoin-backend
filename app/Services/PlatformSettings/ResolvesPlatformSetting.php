<?php

namespace App\Services\PlatformSettings;

use App\Models\PlatformSetting;
use RuntimeException;

class ResolvesPlatformSetting
{
    public function value(string $key): string
    {
        $setting = PlatformSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            throw new RuntimeException("Platform setting [{$key}] is not configured.");
        }

        return (string) $setting->value;
    }

    public function valueOrDefault(string $key, string $default): string
    {
        $setting = PlatformSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return (string) $setting->value;
    }
}
