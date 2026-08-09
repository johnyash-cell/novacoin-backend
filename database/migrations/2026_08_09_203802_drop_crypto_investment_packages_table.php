<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('crypto_investment_packages');
    }

    public function down(): void
    {
        Schema::create('crypto_investment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_pitch', 160);
            $table->text('description');
            $table->string('coingecko_asset_id');
            $table->string('asset_symbol', 32);
            $table->string('asset_label');
            $table->unsignedInteger('term_days');
            $table->decimal('minimum_amount_usd', 12, 2);
            $table->decimal('maximum_amount_usd', 12, 2)->nullable();
            $table->string('fee_type');
            $table->decimal('fee_value', 12, 4);
            $table->boolean('max_loss_enabled')->default(false);
            $table->decimal('max_loss_percent', 5, 2)->nullable();
            $table->unsignedInteger('max_participants');
            $table->unsignedInteger('joined_count')->default(0);
            $table->string('availability_status');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->json('highlights')->nullable();
            $table->timestamps();
        });
    }
};
