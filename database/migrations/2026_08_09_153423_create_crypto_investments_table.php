<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('coingecko_asset_id');
            $table->string('asset_symbol', 32);
            $table->string('asset_label');
            $table->decimal('amount_usd', 12, 2);
            $table->string('fee_type');
            $table->decimal('fee_value', 12, 4);
            $table->string('fee_charge_source');
            $table->decimal('fee_usd', 12, 2);
            $table->decimal('committed_usd', 12, 2);
            $table->decimal('entry_price_usd', 24, 8);
            $table->decimal('units', 24, 12);
            $table->decimal('current_escrow_usd', 12, 2);
            $table->decimal('last_price_usd', 24, 8)->nullable();
            $table->boolean('max_loss_enabled')->default(false);
            $table->decimal('max_loss_floor_usd', 12, 2)->nullable();
            $table->unsignedInteger('term_days');
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('matures_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('payout_completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
            $table->index('matures_at');
            $table->index('coingecko_asset_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_investments');
    }
};
