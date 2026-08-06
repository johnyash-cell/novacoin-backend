<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_reward_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('referred_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('wallet_deposit_id')
                ->unique()
                ->constrained('wallet_deposits')
                ->cascadeOnDelete();
            $table->decimal('amount', 16, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_reward_payouts');
    }
};
