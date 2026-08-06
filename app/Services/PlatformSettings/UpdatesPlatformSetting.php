<?php

namespace App\Services\PlatformSettings;

use App\Models\PlatformSetting;

class UpdatesPlatformSetting
{
    public function update(string $key, string $value): PlatformSetting
    {
        return PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
