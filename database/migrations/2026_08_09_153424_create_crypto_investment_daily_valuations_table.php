<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_investment_daily_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_investment_id')
                ->constrained('crypto_investments')
                ->cascadeOnDelete();
            $table->date('valuation_date');
            $table->decimal('price_usd', 24, 8);
            $table->decimal('escrow_before_usd', 12, 2);
            $table->decimal('escrow_after_usd', 12, 2);
            $table->decimal('delta_usd', 12, 2);
            $table->boolean('was_clamped_by_max_loss')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['crypto_investment_id', 'valuation_date'],
                'crypto_inv_daily_val_unique',
            );
            $table->index('valuation_date', 'crypto_inv_daily_val_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_investment_daily_valuations');
    }
};
