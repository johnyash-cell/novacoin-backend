<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Packages removed — crypto invest uses platform_settings + live assets.
        // Kept so already-recorded migration timestamps stay contiguous on fresh installs
        // that never created the packages table.
        if (Schema::hasTable('crypto_investment_packages')) {
            return;
        }
    }

    public function down(): void
    {
        //
    }
};
