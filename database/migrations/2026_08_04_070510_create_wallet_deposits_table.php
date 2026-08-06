<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('platform_crypto_wallet_id')
                ->constrained('platform_crypto_wallets')
                ->restrictOnDelete();
            $table->decimal('usd_amount', 16, 2);
            $table->decimal('crypto_amount_expected', 24, 12);
            $table->decimal('conversion_rate_usd_per_unit', 24, 8);
            $table->timestamp('quoted_at');
            $table->string('asset_symbol');
            $table->string('network_name');
            $table->string('wallet_address');
            $table->string('proof_image_path');
            $table->string('status');
            $table->text('decline_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->comment('Admin ID from admins table (no FK required)');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_deposits');
    }
};
