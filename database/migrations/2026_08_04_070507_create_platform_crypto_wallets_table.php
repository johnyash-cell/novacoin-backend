<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('asset_symbol');
            $table->string('coingecko_asset_id');
            $table->string('network_name');
            $table->string('wallet_address');
            $table->boolean('is_available_for_funding')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_available_for_funding');
            $table->index('sort_order');
            $table->index('asset_symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_crypto_wallets');
    }
};
