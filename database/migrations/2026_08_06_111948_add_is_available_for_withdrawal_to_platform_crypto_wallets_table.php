<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_crypto_wallets', function (Blueprint $table) {
            $table->boolean('is_available_for_withdrawal')
                ->default(false)
                ->after('is_available_for_funding');
            $table->index('is_available_for_withdrawal');
        });
    }

    public function down(): void
    {
        Schema::table('platform_crypto_wallets', function (Blueprint $table) {
            $table->dropIndex(['is_available_for_withdrawal']);
            $table->dropColumn('is_available_for_withdrawal');
        });
    }
};
